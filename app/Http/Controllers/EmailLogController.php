<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\EmailLog;
use App\Services\Billing\AccountStatementGenerator;
use App\Services\EmailService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private EmailService $emailService,
        private AccountStatementGenerator $statementGenerator,
    ) {}

    // ─── Inbox / Sent list ─────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EmailLog::class);

        $tab       = $request->input('tab', 'inbox'); // inbox | sent
        abort_unless(in_array($tab, ['inbox', 'sent'], true), 422);
        $search    = $request->input('search', '');
        $accountId = $request->input('account_id');

        $query = $tab === 'inbox'
            ? EmailLog::inbound()->with('account:id,name,account_number')
            : EmailLog::outbound()->with('account:id,name,account_number');

        if (!Auth::user()->hasRole('admin')) {
            $userAddress = strtolower((string) Auth::user()->company_email);
            $sharedAddresses = array_map('strtolower', config('mail.shared_addresses', []));
            $visibleAddresses = array_values(array_filter([$userAddress, ...$sharedAddresses]));

            if ($tab === 'inbox') {
                $query->whereIn('recipient_email', $visibleAddresses);
            } else {
                $query->where(function ($sent) use ($visibleAddresses) {
                    $sent->where('sender_user_id', Auth::id())
                        ->orWhereIn('from_email', $visibleAddresses);
                });
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('from_email', 'like', "%{$search}%")
                    ->orWhere('recipient_email', 'like', "%{$search}%");
            });
        }

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $emails      = $query->latest()->paginate(25)->withQueryString();
        $unreadQuery = EmailLog::unread()->inbound();
        if (!Auth::user()->hasRole('admin')) {
            $unreadQuery->whereIn('recipient_email', array_values(array_filter([
                strtolower((string) Auth::user()->company_email),
                ...array_map('strtolower', config('mail.shared_addresses', [])),
            ])));
        }
        $unreadCount = $unreadQuery->count();

        $accounts = Account::whereHas('emailLogs')
            ->select('id', 'name', 'account_number')
            ->orderBy('name')
            ->get();

        return Inertia::render('emails/index', [
            'emails'      => $emails,
            'unreadCount' => $unreadCount,
            'accounts'    => $accounts,
            'filters'     => $request->only(['tab', 'search', 'account_id']),
            'can'         => [
                'compose' => Auth::user()->can('create', EmailLog::class),
            ],
        ]);
    }

    // ─── Thread view ───────────────────────────────────────────────────────

    public function show(EmailLog $emailLog): Response
    {
        $this->authorize('view', $emailLog);

        if ($emailLog->isInbound()) {
            $emailLog->markAsRead();
        }

        $emailLog->load('account:id,name,account_number,email,phone');
        $root = $emailLog->conversationRoot();
        $root->loadMissing('claimant:id,name');
        $isSharedConversation = $this->isSharedConversation($root);
        $canReply = Auth::user()->can('create', EmailLog::class)
            && (!$isSharedConversation || $root->claimed_by === Auth::id());

        $thread = $emailLog->threadEmails()->map(fn($e) => [
            'id'              => $e->id,
            'direction'       => $e->direction,
            'from_email'      => $e->from_email,
            'from_name'       => $e->from_name,
            'recipient_email' => $e->recipient_email,
            'subject'         => $e->subject,
            'body'            => $e->body,
            'status'          => $e->status,
            'attachments'     => $e->attachments,
            'message_id'      => $e->message_id,
            'created_at'      => $e->created_at,
            'read_at'         => $e->read_at,
            'sender_user_id'  => $e->sender_user_id,
        ]);

        return Inertia::render('emails/show', [
            'email'  => $emailLog,
            'thread' => $thread,
            'can'    => [
                'reply'  => $canReply,
                'delete' => Auth::user()->can('delete', $emailLog),
                'claim'  => $isSharedConversation && !$root->claimed_by,
                'canReply' => $canReply,
            ],
            'conversation' => [
                'is_shared' => $isSharedConversation,
                'claimed_by' => $root->claimant?->name,
                'claimed_by_me' => $root->claimed_by === Auth::id(),
            ],
        ]);
    }

    // ─── Compose ──────────────────────────────────────────────────────────

    public function compose(Request $request): Response
    {
        $this->authorize('create', EmailLog::class);

        $initialMode = $request->input('mode') === 'overdue_reminder_individual'
            ? 'overdue_reminder_individual'
            : 'single';

        $preAccount = null;
        if ($acId = $request->input('account_id')) {
            $preAccount = Account::select('id', 'name', 'account_number', 'email')->find($acId);
        }

        $accounts = Account::whereNotNull('email')
            ->where('email', '!=', '')
            ->select('id', 'name', 'account_number', 'email')
            ->orderBy('name')
            ->get();

        $overdueAccountsCount = Account::whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('billings', fn ($query) => $query
                ->whereIn('status', ['pending', 'overdue', 'partially_paid'])
                ->where('due_date', '<', today())
                ->where('amount_due', '>', 0))
            ->count();

        $activeAccountsCount = Account::active()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();

        return Inertia::render('emails/compose', [
            'accounts'               => $accounts,
            'preAccount'             => $preAccount,
            'replyTo'                => null,
            'initialMode'            => $initialMode,
            'overdueAccountsCount'   => $overdueAccountsCount,
            'activeAccountsCount'    => $activeAccountsCount,
            'senderAddresses'        => $this->senderAddresses(),
        ]);
    }

    // ─── Reply ────────────────────────────────────────────────────────────

    public function reply(EmailLog $emailLog): Response
    {
        $this->authorize('create', EmailLog::class);
        $this->authorize('view', $emailLog);
        abort_unless($emailLog->isInbound(), 404);
        $root = $emailLog->conversationRoot();
        $this->ensureReplyClaim($root);

        $accounts = Account::whereNotNull('email')
            ->where('email', '!=', '')
            ->select('id', 'name', 'account_number', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('emails/compose', [
            'accounts'   => $accounts,
            'preAccount' => $emailLog->account,
            'replyTo'    => [
                'id'         => $emailLog->id,
                'from_email' => $emailLog->from_email,
                'from_name'  => $emailLog->from_name,
                'subject'    => $emailLog->subject,
                'message_id' => $emailLog->message_id,
                'from_address' => $emailLog->recipient_email,
            ],
            'initialMode' => 'single',
            'overdueAccountsCount' => 0,
            'activeAccountsCount'  => 0,
            'senderAddresses' => $this->senderAddresses(),
        ]);
    }

    public function claim(EmailLog $emailLog): RedirectResponse
    {
        $this->authorize('view', $emailLog);
        $root = $emailLog->conversationRoot();
        abort_unless($this->isSharedConversation($root), 404);

        $claimed = DB::transaction(function () use ($root) {
            $lockedRoot = EmailLog::whereKey($root->id)->lockForUpdate()->firstOrFail();
            if ($lockedRoot->claimed_by && $lockedRoot->claimed_by !== Auth::id()) {
                return false;
            }
            if (!$lockedRoot->claimed_by) {
                $lockedRoot->update(['claimed_by' => Auth::id(), 'claimed_at' => now()]);
            }

            return true;
        });

        return back()->with(
            $claimed ? 'success' : 'error',
            $claimed ? 'You claimed this conversation.' : 'This conversation has already been claimed by another user.'
        );
    }

    // ─── Send ─────────────────────────────────────────────────────────────

    public function send(Request $request): RedirectResponse
    {
        $this->authorize('create', EmailLog::class);

        $data = $request->validate([
            'to'          => 'required|email',
            'subject'     => 'required|string|max:255',
            'body'        => 'required|string',
            'account_id'  => 'nullable|exists:accounts,id',
            'in_reply_to' => 'nullable|string|max:255',
            'from_email' => 'required|email',
        ]);

        $senderAddresses = array_column($this->senderAddresses(), 'email');
        abort_unless(in_array(strtolower($data['from_email']), $senderAddresses, true), 403);
        $parent = !empty($data['in_reply_to'])
            ? EmailLog::where('message_id', $data['in_reply_to'])->first()
            : null;
        if (!empty($data['in_reply_to'])) {
            abort_unless($parent && $parent->isInbound(), 422, 'The message being replied to could not be found.');
            $this->authorize('view', $parent);
            $root = $parent->conversationRoot();
            $this->ensureReplyClaim($root);
            abort_unless(
                strtolower((string) $parent->recipient_email) === strtolower($data['from_email']),
                403,
                'Replies must be sent from the address that received the original message.'
            );
        }

        $log = $this->emailService->send(
            $data['to'],
            $data['subject'],
            $data['body'],
            array_filter([
                'account_id'  => $data['account_id']  ?? null,
                'in_reply_to' => $data['in_reply_to'] ?? null,
                'from_address' => strtolower($data['from_email']),
                'sender_user_id' => Auth::id(),
                'thread_id' => $parent?->conversationRoot()->id,
            ])
        );

        if ($log->status === EmailLog::STATUS_FAILED) {
            return redirect()->route('emails.show', $log)
                ->with('error', 'Email failed to send: ' . $log->error_message);
        }

        return redirect()->route('emails.show', $log)
            ->with('success', 'Email sent successfully.');
    }

    public function sendOverdue(Request $request): RedirectResponse
    {
        $this->authorize('create', EmailLog::class);
        abort_unless(Auth::user()->hasRole('admin'), 403);

        $data = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'subject'    => 'required|string|max:255',
            'body'       => 'required|string|max:20000',
        ]);

        $account = Account::findOrFail($data['account_id']);
        $log = $this->sendOverdueReminder($account, $data['subject'], $data['body']);

        if (!$log) {
            return back()->with('error', 'This account has no overdue balance or saved email address.');
        }

        if ($log->status === EmailLog::STATUS_FAILED) {
            return redirect()->route('emails.show', $log)
                ->with('error', 'Email failed to send: ' . $log->error_message);
        }

        return redirect()->route('emails.show', $log)
            ->with('success', "Overdue reminder sent to {$account->email} with the account statement attached.");
    }

    public function sendBulk(Request $request): RedirectResponse
    {
        $this->authorize('create', EmailLog::class);
        abort_unless(Auth::user()->hasRole('admin'), 403);

        $data = $request->validate([
            'campaign' => 'required|in:overdue_reminder,announcement',
            'subject'  => 'required|string|max:255',
            'body'     => 'required|string|max:20000',
        ]);

        $sent = 0;
        $failed = 0;
        $eligible = 0;

        if ($data['campaign'] === 'overdue_reminder') {
            Account::whereNotNull('email')
                ->where('email', '!=', '')
                ->whereHas('billings', fn ($query) => $query
                    ->whereIn('status', ['pending', 'overdue', 'partially_paid'])
                    ->where('due_date', '<', today())
                    ->where('amount_due', '>', 0))
                ->orderBy('id')
                ->chunkById(100, function ($accounts) use ($data, &$sent, &$failed, &$eligible) {
                    foreach ($accounts as $account) {
                        $log = $this->sendOverdueReminder($account, $data['subject'], $data['body']);
                        if (!$log) {
                            continue;
                        }
                        $eligible++;
                        $log->status === EmailLog::STATUS_FAILED ? $failed++ : $sent++;
                    }
                });
        } else {
            Account::active()
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($accounts) use ($data, &$sent, &$failed, &$eligible) {
                    foreach ($accounts as $account) {
                        $eligible++;

                        $log = $this->emailService->send(
                            $account->email,
                            $data['subject'],
                            $data['body'],
                            ['account_id' => $account->id, 'name' => $account->name]
                        );

                        $log->status === EmailLog::STATUS_FAILED ? $failed++ : $sent++;
                    }
                });
        }

        $redirect = redirect()->route('emails.index');

        if ($failed > 0) {
            return $redirect->with('error', "Bulk email completed with failures: {$sent} sent, {$failed} failed. See the email log for details.");
        }

        if ($eligible === 0) {
            return $redirect->with('warning', 'No eligible accounts were found for this bulk email.');
        }

        return $redirect->with('success', "Bulk email complete: {$sent} sent, {$failed} failed.");
    }

    private function sendOverdueReminder(Account $account, string $subject, string $body): ?EmailLog
    {
        if (!$account->email) {
            return null;
        }

        $overdueAmount = $account->billings()
            ->whereIn('status', ['pending', 'overdue', 'partially_paid'])
            ->where('due_date', '<', today())
            ->where('amount_due', '>', 0)
            ->sum('amount_due');

        if ($overdueAmount <= 0) {
            return null;
        }

        $personalizedBody = strtr($body, [
            '{{name}}' => e($account->name),
            '{{account_number}}' => e($account->account_number),
            '{{overdue_amount}}' => number_format((float) $overdueAmount, 2) . ' KES',
        ]);
        $periodStart = now()->startOfYear()->toDateString();
        $periodEnd = today()->toDateString();
        $statementPdf = $this->statementGenerator
            ->generatePdf($account, $periodStart, $periodEnd)
            ->output();

        return $this->emailService->send(
            $account->email,
            $subject,
            $personalizedBody,
            [
                'account_id' => $account->id,
                'name' => $account->name,
                'attachments' => [[
                    'name' => "account_statement_{$account->account_number}.pdf",
                    'mime' => 'application/pdf',
                    'content' => $statementPdf,
                ]],
            ]
        );
    }

    // ─── Mark read ────────────────────────────────────────────────────────

    public function markRead(EmailLog $emailLog): RedirectResponse
    {
        $this->authorize('update', $emailLog);
        $emailLog->markAsRead();
        return back()->with('success', 'Marked as read.');
    }

    // ─── Delete ───────────────────────────────────────────────────────────

    public function destroy(EmailLog $emailLog): RedirectResponse
    {
        $this->authorize('delete', $emailLog);
        $emailLog->delete();
        return redirect()->route('emails.index')
            ->with('success', 'Email deleted from logs.');
    }

    private function senderAddresses(): array
    {
        $addresses = [];
        $user = Auth::user();

        if ($user->company_email) {
            $addresses[] = [
                'email' => strtolower($user->company_email),
                'label' => "{$user->name} <{$user->company_email}>",
            ];
        }

        foreach (config('mail.shared_addresses', []) as $address) {
            $address = strtolower($address);
            $addresses[] = ['email' => $address, 'label' => $address . ' (shared)'];
        }

        return $addresses;
    }

    private function ensureReplyClaim(EmailLog $root): void
    {
        if ($this->isSharedConversation($root) && $root->claimed_by !== Auth::id()) {
            abort(403, $root->claimed_by
                ? 'This shared conversation is claimed by another user.'
                : 'Claim this shared conversation before replying.');
        }
    }

    private function isSharedConversation(EmailLog $emailLog): bool
    {
        $sharedAddresses = array_map('strtolower', config('mail.shared_addresses', []));

        return in_array(strtolower((string) $emailLog->recipient_email), $sharedAddresses, true)
            || in_array(strtolower((string) $emailLog->from_email), $sharedAddresses, true);
    }
}
