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
        $providerId = $data['email_id'] ?? null;

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
        $providerId = $data['email_id'] ?? null;
        $recipient = $this->firstRecipient($data['to'] ?? []);
        $log = $this->findOutboundLog($providerId, $recipient, $data['subject'] ?? null);

        if (!$log) {
            Log::info('Resend event for untracked message', [
                'type' => $payload['type'] ?? null,
                'provider_id' => $providerId,
                'recipient' => $recipient,
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

    private function findOutboundLog(?string $providerId, ?string $recipient, ?string $subject): ?EmailLog
    {
        if ($providerId) {
            $log = EmailLog::outbound()->where('provider_id', $providerId)->first();
            if ($log) {
                return $log;
            }
        }

        if ($recipient && $subject) {
            return EmailLog::outbound()
                ->where('recipient_email', $recipient)
                ->where('subject', $subject)
                ->latest()
                ->first();
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

    private function parseAddress(string $value): array
    {
        $value = trim($value);

        if (preg_match('/^(?:"?([^"]*)"?\s)?<([^>]+)>$/', $value, $matches)) {
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

        if (is_array($to) && isset($to[0]) && is_string($to[0])) {
            return strtolower(trim($to[0]));
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
