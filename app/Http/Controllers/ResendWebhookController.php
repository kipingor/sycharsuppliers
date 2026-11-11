<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Resend\Contracts\Client as ResendClient;
use Resend\WebhookSignature;
use Throwable;

class ResendWebhookController extends Controller
{
    public function __construct(private ResendClient $resend) {}

    public function handle(Request $request): JsonResponse
    {
        if (!$this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->json()->all();
        $type = $payload['type'] ?? null;

        if (!$type || !isset($payload['data']) || !is_array($payload['data'])) {
            return response()->json(['error' => 'Malformed payload'], 400);
        }

        return match ($type) {
            'email.received' => $this->handleReceived($payload),
            'email.sent',
            'email.delivered',
            'email.delivery_delayed',
            'email.opened',
            'email.clicked',
            'email.bounced',
            'email.failed',
            'email.complained' => $this->handleOutboundEvent($payload),
            default => response()->json(['status' => 'ignored'], 200),
        };
    }

    private function handleReceived(array $payload): JsonResponse
    {
        $data = $payload['data'];
        $providerId = $this->getProviderId($data);

        if ($providerId && EmailLog::inbound()->where('provider_id', $providerId)->exists()) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        $details = $this->fetchInboundEmailDetails($providerId);
        $from = $details['from'] ?? $data['from'] ?? '';
        [$fromName, $fromEmail] = $this->parseAddress($from);

        $recipient = $this->appRecipient($details['to'] ?? $data['to'] ?? []);
        $subject = $details['subject'] ?? $data['subject'] ?? '(no subject)';
        $messageId = $this->cleanMessageId($details['message_id'] ?? $data['message_id'] ?? null);
        $headers = $details['headers'] ?? $data['headers'] ?? [];
        $inReplyTo = $this->extractHeader($headers, 'In-Reply-To');
        $body = $this->buildBody(
            $details['html'] ?? $data['html'] ?? null,
            $details['text'] ?? $data['text'] ?? null
        );

        $parent = $inReplyTo
            ? EmailLog::where('message_id', $inReplyTo)->first()
            : null;
        $threadId = $parent ? ($parent->thread_id ?? $parent->id) : null;

        $emailLog = EmailLog::create([
            'direction'       => 'inbound',
            'from_email'      => $fromEmail,
            'from_name'       => $fromName ?: null,
            'account_id'      => EmailLog::resolveAccountId($fromEmail),
            'recipient_email' => $recipient,
            'subject'         => $subject,
            'body'            => $body,
            'status'          => EmailLog::STATUS_RECEIVED,
            'message_id'      => $messageId,
            'provider_id'     => $providerId,
            'in_reply_to'     => $inReplyTo,
            'thread_id'       => $threadId,
            'attachments'     => $this->mapAttachments($details['attachments'] ?? $data['attachments'] ?? []),
            'raw_payload'     => $payload,
        ]);
        if (!$threadId) {
            $emailLog->update(['thread_id' => $emailLog->id]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function handleOutboundEvent(array $payload): JsonResponse
    {
        $data = $payload['data'];
        $providerId = $this->getProviderId($data);
        $recipient = $this->firstRecipient($data['to'] ?? []);
        $messageId = $this->cleanMessageId($data['message_id'] ?? null);
        $eventAt = isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : now();
        $log = $this->findOutboundLog($providerId, $recipient, $data['subject'] ?? null, $messageId, $eventAt);

        if (!$log) {
            Log::info('Resend event for untracked message', [
                'type' => $payload['type'] ?? null,
                'provider_id' => $providerId,
                'recipient' => $recipient,
                'message_id' => $messageId,
            ]);

            return response()->json(['status' => 'ok'], 200);
        }

        $this->applyOutboundEvent($log, $payload);

        return response()->json(['status' => 'ok'], 200);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('resend.webhook.secret');

        if (!$secret) {
            if (app()->isLocal() || app()->runningUnitTests()) {
                return true;
            }

            Log::error('RESEND_WEBHOOK_SECRET is not configured.');

            return false;
        }

        try {
            $headers = collect($request->headers->all())
                ->mapWithKeys(fn ($value, $key) => [$key => is_array($value) ? ($value[0] ?? '') : $value])
                ->all();

            WebhookSignature::verify(
                $request->getContent(),
                array_change_key_case($headers, CASE_LOWER),
                $secret,
                (int) config('resend.webhook.tolerance', 300)
            );

            return true;
        } catch (Throwable $e) {
            Log::warning('Resend webhook signature verification failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return false;
        }
    }

    private function fetchInboundEmailDetails(?string $providerId): array
    {
        if (!$providerId) {
            return [];
        }

        try {
            $email = $this->resend->emails->receiving->get($providerId);

            return [
                'from' => $email->from ?? null,
                'to' => $email->to ?? [],
                'subject' => $email->subject ?? null,
                'html' => $email->html ?? null,
                'text' => $email->text ?? null,
                'message_id' => $email->message_id ?? null,
                'headers' => $email->headers ?? [],
                'attachments' => $email->attachments ?? [],
            ];
        } catch (Throwable $e) {
            Log::warning('Failed to fetch inbound email details from Resend', [
                'provider_id' => $providerId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function findOutboundLog(
        ?string $providerId,
        ?string $recipient,
        ?string $subject,
        ?string $messageId,
        Carbon $eventAt,
    ): ?EmailLog
    {
        if ($providerId) {
            $log = EmailLog::outbound()->where('provider_id', $providerId)->first();
            if ($log) {
                return $log;
            }
        }

        if ($messageId) {
            $log = EmailLog::outbound()->where('message_id', $messageId)->first();
            if ($log) {
                return $log;
            }
        }

        if (!$recipient || !$subject) {
            return null;
        }

        $candidates = EmailLog::outbound()
            ->whereNull('provider_id')
            ->where('recipient_email', $recipient)
            ->where('subject', $subject)
            ->whereIn('status', [EmailLog::STATUS_QUEUED, EmailLog::STATUS_SENT, EmailLog::STATUS_FAILED])
            ->whereBetween('created_at', [$eventAt->copy()->subMinutes(15), $eventAt->copy()->addMinute()])
            ->limit(2)
            ->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function applyOutboundEvent(EmailLog $log, array $payload): void
    {
        $type = $payload['type'];
        $data = $payload['data'];
        $eventAt = isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : now();
        $previousEventAt = data_get($log->raw_payload, 'created_at');

        if ($previousEventAt && Carbon::parse($previousEventAt)->greaterThan($eventAt)) {
            return;
        }

        $nextStatus = match ($type) {
            'email.sent' => EmailLog::STATUS_SENT,
            'email.delivered' => EmailLog::STATUS_DELIVERED,
            'email.delivery_delayed' => EmailLog::STATUS_QUEUED,
            'email.opened' => EmailLog::STATUS_OPENED,
            'email.clicked' => EmailLog::STATUS_CLICKED,
            'email.bounced' => EmailLog::STATUS_BOUNCED,
            'email.failed' => EmailLog::STATUS_FAILED,
            'email.complained' => EmailLog::STATUS_COMPLAINED,
            default => $log->status,
        };

        if ($this->statusRank($nextStatus) < $this->statusRank($log->status)) {
            $nextStatus = $log->status;
        }

        $base = [
            'provider_id' => $this->getProviderId($data) ?? $log->provider_id,
            'raw_payload' => $payload,
            'status' => $nextStatus,
        ];

        if (
            $this->statusRank($nextStatus) > $this->statusRank($log->status)
            && in_array($nextStatus, [
                EmailLog::STATUS_SENT,
                EmailLog::STATUS_DELIVERED,
                EmailLog::STATUS_OPENED,
                EmailLog::STATUS_CLICKED,
            ], true)
        ) {
            $base['error_message'] = null;
        }

        match ($type) {
            'email.sent' => $log->update($base + [
                'sent_at' => $log->sent_at ?? $eventAt,
            ]),
            'email.delivered' => $log->update($base + [
                'delivered_at' => $log->delivered_at ?? $eventAt,
            ]),
            'email.delivery_delayed' => $log->update($base),
            'email.opened' => $log->update($base + [
                'opened_at' => $log->opened_at ?? $eventAt,
            ]),
            'email.clicked' => $log->update($base),
            'email.bounced' => $log->update($base + [
                'bounced_at' => $log->bounced_at ?? $eventAt,
                'error_message' => $this->eventError($data) ?? $log->error_message,
            ]),
            'email.failed' => $log->update($base + [
                'error_message' => $this->eventError($data) ?? $log->error_message,
            ]),
            'email.complained' => $log->update($base),
            default => null,
        };
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            EmailLog::STATUS_SENT => 1,
            EmailLog::STATUS_FAILED => 2,
            EmailLog::STATUS_BOUNCED => 3,
            EmailLog::STATUS_DELIVERED => 4,
            EmailLog::STATUS_OPENED => 5,
            EmailLog::STATUS_CLICKED => 6,
            EmailLog::STATUS_COMPLAINED => 7,
            default => 0,
        };
    }

    private function eventError(array $data): ?string
    {
        $message = data_get($data, 'bounce.message')
            ?? data_get($data, 'error.message')
            ?? $data['message']
            ?? $data['reason']
            ?? null;

        return is_string($message) ? $message : null;
    }

    private function buildBody(?string $html, ?string $text): string
    {
        if ($html) {
            return $html;
        }

        if ($text) {
            return nl2br(e($text));
        }

        return '<p>(no body)</p>';
    }

    private function parseAddress(string|array|object|null $value): array
    {
        if (is_array($value)) {
            if (isset($value['email'])) {
                return [trim($value['name'] ?? ''), strtolower(trim($value['email']))];
            }

            if (isset($value[0]) && is_string($value[0])) {
                return ['', strtolower(trim($value[0]))];
            }

            return ['', ''];
        }

        if (is_object($value) && property_exists($value, 'email')) {
            return [trim($value->name ?? ''), strtolower(trim($value->email))];
        }

        if (!is_string($value)) {
            return ['', ''];
        }

        $value = trim($value);

        if (preg_match('/^(?:"?([^"\n]*)"?\s)?<([^>]+)>$/', $value, $matches)) {
            return [trim($matches[1] ?? ''), strtolower(trim($matches[2]))];
        }

        return ['', strtolower($value)];
    }

    private function cleanMessageId(?string $messageId): ?string
    {
        if (!$messageId) {
            return null;
        }

        return trim($messageId, " <>\t\n\r\0\x0B");
    }

    private function getProviderId(array $data): ?string
    {
        if (isset($data['email_id']) && is_string($data['email_id'])) {
            return $data['email_id'];
        }

        if (isset($data['email'])) {
            if (is_array($data['email']) && isset($data['email']['id']) && is_string($data['email']['id'])) {
                return $data['email']['id'];
            }

            if (is_object($data['email']) && property_exists($data['email'], 'id') && is_string($data['email']->id)) {
                return $data['email']->id;
            }
        }

        if (isset($data['id']) && is_string($data['id'])) {
            return $data['id'];
        }

        return null;
    }

    private function extractHeader(array $headers, string $name): ?string
    {
        $target = strtolower($name);

        foreach ($headers as $key => $value) {
            if (is_string($key) && strtolower($key) === $target) {
                return $this->cleanMessageId(is_array($value) ? ($value[0] ?? null) : $value);
            }

            if (is_array($value) && strtolower($value['name'] ?? '') === $target) {
                return $this->cleanMessageId($value['value'] ?? null);
            }
        }

        return null;
    }

    private function firstRecipient(array|string|null $to): ?string
    {
        if (is_string($to)) {
            return strtolower(trim($to));
        }

        if (is_array($to)) {
            if (isset($to[0])) {
                return $this->normalizeEmail($to[0]);
            }

            return $this->normalizeEmail($to);
        }

        if (is_object($to) && property_exists($to, 'email')) {
            return strtolower(trim($to->email));
        }

        return null;
    }

    private function appRecipient(array|string|null $to): ?string
    {
        $recipients = is_array($to) ? $to : [$to];
        $normalized = array_values(array_filter(array_map(
            fn ($recipient) => $this->normalizeEmail($recipient),
            $recipients
        )));

        $shared = array_map('strtolower', config('mail.shared_addresses', []));
        $individual = User::whereNotNull('company_email')
            ->pluck('company_email')
            ->map(fn ($email) => strtolower($email))
            ->all();

        foreach ([...$shared, ...$individual] as $appAddress) {
            if (in_array($appAddress, $normalized, true)) {
                return $appAddress;
            }
        }

        return $normalized[0] ?? null;
    }

    private function normalizeEmail(array|object|string|null $recipient): ?string
    {
        if (is_string($recipient)) {
            return strtolower(trim($recipient));
        }

        if (is_array($recipient)) {
            if (isset($recipient['email'])) {
                return strtolower(trim($recipient['email']));
            }

            if (isset($recipient[0]) && is_string($recipient[0])) {
                return strtolower(trim($recipient[0]));
            }
        }

        if (is_object($recipient) && property_exists($recipient, 'email')) {
            return strtolower(trim($recipient->email));
        }

        return null;
    }

    private function mapAttachments(array $attachments): ?array
    {
        if ($attachments === []) {
            return null;
        }

        return array_map(function ($attachment) {
            if (is_array($attachment)) {
                return [
                    'id' => $attachment['id'] ?? null,
                    'name' => $attachment['filename'] ?? $attachment['name'] ?? null,
                    'mime' => $attachment['content_type'] ?? $attachment['mime'] ?? null,
                    'size' => $attachment['size'] ?? null,
                    'content_id' => $attachment['content_id'] ?? null,
                ];
            }

            return [
                'id' => $attachment->id ?? null,
                'name' => $attachment->filename ?? null,
                'mime' => $attachment->content_type ?? null,
                'size' => $attachment->size ?? null,
                'content_id' => $attachment->content_id ?? null,
            ];
        }, $attachments);
    }
}
