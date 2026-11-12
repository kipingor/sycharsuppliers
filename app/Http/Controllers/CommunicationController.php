<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\PriorityMessage;
use App\Models\SystemAnnouncement;
use App\Models\User;
use App\Notifications\InternalAlertNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommunicationController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $unread = $user->unreadNotifications()->latest()->limit(8)->get();
        $priorities = PriorityMessage::query()
            ->with('sender:id,name')
            ->where('recipient_id', $user->id)
            ->whereNull('acknowledged_at')
            ->latest()
            ->get();

        return response()->json([
            'notifications' => [
                'count' => $user->unreadNotifications()->count(),
                'items' => $unread->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Notification',
                    'body' => $notification->data['body'] ?? '',
                    'created_at' => $notification->created_at,
                ]),
            ],
            'priorities' => $priorities->map(fn (PriorityMessage $message) => [
                'id' => $message->id,
                'sender' => $message->sender->name,
                'message' => $message->message,
                'priority_level' => $message->priority_level,
                'read_at' => $message->read_at,
                'acknowledged_at' => $message->acknowledged_at,
                'created_at' => $message->created_at,
            ]),
            'announcements' => SystemAnnouncement::query()
                ->whereNotNull('published_at')
                ->latest('published_at')
                ->limit(5)
                ->get(['id', 'title', 'body', 'priority_level', 'published_at']),
            'operations' => [
                'failed_payments' => \App\Models\Payment::query()->where('status', 'failed')->count(),
                'overdue_bills' => \App\Models\Billing::query()->where(function (Builder $query) {
                    $query->where('status', 'overdue')
                        ->orWhere(function (Builder $query) {
                            $query->whereIn('status', ['pending', 'partially_paid'])
                                ->whereDate('due_date', '<', now()->toDateString());
                        });
                })->count(),
                'pending_readings' => max(
                    0,
                    \App\Models\Meter::query()->count() - \App\Models\MeterReading::query()
                        ->whereDate('reading_date', '>=', now()->startOfMonth()->toDateString())
                        ->distinct('meter_id')
                        ->count('meter_id'),
                ),
            ],
            'recipients' => [
                'users' => User::query()->whereKeyNot($user->id)->orderBy('name')->get(['id', 'name']),
                'roles' => \Spatie\Permission\Models\Role::query()->orderBy('name')->pluck('name'),
            ],
        ]);
    }

    public function markNotificationRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function sendPriority(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['nullable', 'required_without:recipient_role', 'prohibits:recipient_role', 'integer', 'exists:users,id'],
            'recipient_role' => ['nullable', 'required_without:recipient_id', 'prohibits:recipient_id', 'string', 'exists:roles,name'],
            'message' => ['required', 'string', 'max:2000'],
            'priority_level' => ['required', Rule::in(['critical', 'high', 'normal'])],
        ]);
        abort_if(isset($validated['recipient_id']) && (int) $validated['recipient_id'] === $request->user()->id, 422, 'You cannot send a priority message to yourself.');

        $recipients = isset($validated['recipient_role'])
            ? User::role($validated['recipient_role'])->whereKeyNot($request->user()->id)->get()
            : User::whereKey($validated['recipient_id'])->get();
        abort_if($recipients->isEmpty(), 422, 'The selected role has no users.');

        $messages = DB::transaction(function () use ($request, $validated, $recipients) {
            return $recipients->map(function (User $recipient) use ($request, $validated) {
                $priority = PriorityMessage::create([
                    'sender_id' => $request->user()->id,
                    'recipient_id' => $recipient->id,
                    'message' => $validated['message'],
                    'priority_level' => $validated['priority_level'],
                ]);
                $recipient->notify(new InternalAlertNotification(
                    'Priority message from '.$request->user()->name,
                    $validated['message'],
                    $priority->id,
                ));

                return $priority;
            });
        });

        return response()->json(['sent' => $messages->count()], 201);
    }

    public function acknowledgePriority(Request $request, PriorityMessage $priorityMessage): JsonResponse
    {
        abort_unless($priorityMessage->recipient_id === $request->user()->id, 403);
        $priorityMessage->update([
            'read_at' => $priorityMessage->read_at ?? now(),
            'acknowledged_at' => now(),
        ]);

        $request->user()->unreadNotifications()
            ->where('data->priority_message_id', $priorityMessage->id)
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
            ->with(['participants:id,name', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->latest('updated_at')
            ->get()
            ->map(function (Conversation $conversation) use ($user) {
                $participant = $conversation->participants->firstWhere('id', '!=', $user->id);

                return [
                    'id' => $conversation->id,
                    'user' => $participant ? ['id' => $participant->id, 'name' => $participant->name] : null,
                    'last_message' => $conversation->messages->first()?->body,
                    'unread_count' => $conversation->messages()
                        ->where('sender_id', '!=', $user->id)
                        ->whereNull('read_at')
                        ->count(),
                ];
            });

        return response()->json([
            'conversations' => $conversations,
            'users' => User::query()->whereKeyNot($user->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function startConversation(Request $request): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        abort_if((int) $validated['user_id'] === $request->user()->id, 422, 'You cannot start a conversation with yourself.');

        $ids = [(int) $request->user()->id, (int) $validated['user_id']];
        sort($ids);
        $conversation = DB::transaction(function () use ($ids, $request) {
            $conversation = Conversation::firstOrCreate([
                'direct_key' => implode(':', $ids),
            ], ['created_by' => $request->user()->id]);
            foreach ($ids as $id) {
                $conversation->participants()->syncWithoutDetaching([$id]);
            }

            return $conversation;
        });

        return response()->json(['id' => $conversation->id], 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $participant = $conversation->participants()->whereKey($request->user()->id)->firstOrFail();
        $readAt = now();
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => $readAt]);
        $conversation->messages()->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')->update(['read_at' => $readAt]);
        $request->user()->unreadNotifications()
            ->whereIn('data->chat_message_id', $conversation->messages()->select('id'))
            ->update(['read_at' => $readAt]);

        return response()->json([
            'messages' => $conversation->messages()->with('sender:id,name')->latest()
                ->limit(100)->get()->reverse()->values()->map(fn (ChatMessage $message) => [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender' => $message->sender->name,
                    'body' => $message->body,
                    'created_at' => $message->created_at,
                ]),
        ]);
    }

    public function sendChatMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation->participants()->whereKey($request->user()->id)->firstOrFail();
        $validated = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);
        $conversation->touch();
        $conversation->participants()->where('user_id', '!=', $request->user()->id)
            ->get()->each(function ($participant) use ($request, $message) {
                $participant->notify(new InternalAlertNotification(
                    'New message from '.$request->user()->name,
                    $message->body,
                    chatMessageId: $message->id,
                ));
            });

        return response()->json(['id' => $message->id], 201);
    }

    public function publishAnnouncement(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage', \App\Models\SystemSetting::class), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'priority_level' => ['required', Rule::in(['critical', 'high', 'normal'])],
        ]);
        $announcement = SystemAnnouncement::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        return response()->json(['id' => $announcement->id], 201);
    }
}
