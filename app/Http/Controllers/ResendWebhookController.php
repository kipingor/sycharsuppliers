<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $recipient = $this->firstRecipient($details['to'] ?? $data['to'] ?? []);
        $subject = $details['subject'] ?? $data['subject'] ?? '(no subject)';
        $messageId = $this->cleanMessageId($details['message_id'] ?? $data['message_id'] ?? null);
        $headers = $details['headers'] ?? $data['headers'] ?? [];
        $inReplyTo = $this->extractHeader($headers, 'In-Reply-To');
        $body = $this->buildBody(
            $details['html'] ?? $data['html'] ?? null,
            $details['text'] ?? $data['text'] ?? null
        );

        EmailLog::create([
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
            'attachments'     => $this->mapAttachments($details['attachments'] ?? $data['attachments'] ?? []),
            'raw_payload'     => $payload,
        ]);

        return response()->json(['status' => 'ok'], 200);
    }

    private function handleOutboundEvent(array $payload): JsonResponse
    {
        $data = $payload['data'];
        $providerId = $this->getProviderId($data);
        $recipient = $this->firstRecipient($data['to'] ?? []);
        $messageId = $this->cleanMessageId($data['message_id'] ?? null);
        $log = $this->findOutboundLog($providerId, $recipient, $data['subject'] ?? null, $messageId);

        if (!$log) {
            Log::info('Resend event for untracked message', [
                'type' => $payload['type'] ?? null,
                'provider_id' => $providerId,
                'recipient' => $recipient,
                'message_id' => $messageId,
            ]);

            return response()->json(['status' => 'ok'], 200);
        }

        $this->applyOutboundEvent($log, $payload['type'], $data);

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

    private function findOutboundLog(?string $providerId, ?string $recipient, ?string $subject, ?string $messageId = null): ?EmailLog
    {
        if ($providerId) {
            $log = EmailLog::outbound()->where('provider_id', $providerId)->first();
            if ($log) {
                return $log;
            }
        }

        if ($recipient && $subject) {
            $log = EmailLog::outbound()
                ->where('recipient_email', $recipient)
                ->where('subject', $subject)
                ->latest()
                ->first();

            if ($log) {
                return $log;
            }
        }

        if ($messageId) {
            return EmailLog::outbound()->where('message_id', $messageId)->first();
        }

        return null;
    }

    private function applyOutboundEvent(EmailLog $log, string $type, array $data): void
    {
        $base = [
            'provider_id' => $data['email_id'] ?? $log->provider_id,
            'raw_payload' => $data + ['type' => $type],
        ];

        match ($type) {
            'email.sent' => $log->update($base + [
                'status' => EmailLog::STATUS_SENT,
                'sent_at' => $log->sent_at ?? now(),
            ]),
            'email.delivered' => $log->update($base + [
                'status' => EmailLog::STATUS_DELIVERED,
                'delivered_at' => $log->delivered_at ?? now(),
            ]),
            'email.delivery_delayed' => $log->update($base + [
                'status' => EmailLog::STATUS_QUEUED,
            ]),
            'email.opened' => $log->update($base + [
                'status' => EmailLog::STATUS_OPENED,
                'opened_at' => $log->opened_at ?? now(),
            ]),
            'email.clicked' => $log->update($base + [
                'status' => EmailLog::STATUS_CLICKED,
            ]),
            'email.bounced' => $log->update($base + [
                'status' => EmailLog::STATUS_BOUNCED,
                'bounced_at' => $log->bounced_at ?? now(),
                'error_message' => $data['bounce']['message'] ?? $data['message'] ?? $log->error_message,
            ]),
            'email.failed' => $log->update($base + [
                'status' => EmailLog::STATUS_FAILED,
                'error_message' => $data['message'] ?? $log->error_message,
            ]),
            'email.complained' => $log->update($base + [
                'status' => EmailLog::STATUS_COMPLAINED,
            ]),
            default => null,
        };
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
