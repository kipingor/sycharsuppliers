<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * MailgunWebhookController
 * ─────────────────────────────────────────────────────────────────────────────
 * Handles two Mailgun webhook POST endpoints (both in api.php — no CSRF,
 * no session, no auth middleware):
 *
 *   POST /api/webhooks/mailgun/inbound   — Email Receiving (multipart/form-data)
 *   POST /api/webhooks/mailgun/events    — Delivery/tracking events (JSON)
 *
 * Both verify Mailgun's HMAC-SHA256 signing key before processing.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Mailgun Dashboard Configuration
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  OUTBOUND TRACKING EVENTS
 *    Sending → Webhooks → Add webhook
 *    Select events: Delivered, Failed, Complained, Opened, Clicked, Unsubscribed
 *    URL: https://yourdomain.com/api/webhooks/mailgun/events
 *
 *  INBOUND EMAIL (Email Receiving)
 *    Receiving → Routes → Create Route
 *    Expression type: Match Recipient
 *    Expression:      billing@yourdomain.com
 *                     (or use catch_all() to catch everything)
 *    Action:          forward("https://yourdomain.com/api/webhooks/mailgun/inbound")
 *    Priority:        10
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Required .env Variables
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *   MAIL_MAILER=mailgun
 *   MAIL_FROM_ADDRESS=billing@yourdomain.com
 *   MAIL_FROM_NAME="Your Company Name"
 *
 *   MAILGUN_DOMAIN=mg.yourdomain.com
 *   MAILGUN_SECRET=key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 *   MAILGUN_ENDPOINT=api.mailgun.net        # use api.eu.mailgun.net for EU region
 *   MAILGUN_WEBHOOK_SIGNING_KEY=xxxxxxxx    # DIFFERENT from MAILGUN_SECRET
 *
 * Find MAILGUN_WEBHOOK_SIGNING_KEY at:
 *   Mailgun Dashboard → Sending → Webhooks → (the key icon at the top of the page)
 */
class MailgunWebhookController extends Controller
{
    // ─── Inbound email ─────────────────────────────────────────────────────

    /**
     * Mailgun inbound route forwarding.
     *
     * Mailgun POSTs multipart/form-data including:
     *   sender, from, recipient, subject, body-plain, body-html,
     *   Message-Id, In-Reply-To, attachment-count, attachment-{n},
     *   timestamp, token, signature
     */
    public function inbound(Request $request): JsonResponse
    {
        if (!$this->verifySignature(
            $request->input('timestamp', ''),
            $request->input('token', ''),
            $request->input('signature', '')
        )) {
            Log::warning('Mailgun inbound: invalid signature', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Deduplicate: Mailgun retries on non-2xx responses
        $token = $request->input('token', '');
        if ($token && EmailLog::where('mailgun_id', $token)->exists()) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        // ── Parse sender ──────────────────────────────────────────────────
        $rawFrom                = $request->input('from', $request->input('sender', ''));
        [$fromName, $fromEmail] = $this->parseAddress($rawFrom);

        // ── Core fields ───────────────────────────────────────────────────
        $recipient = $request->input('recipient', '');
        $subject   = $request->input('subject')   ?: '(no subject)';
        $bodyHtml  = $request->input('body-html')  ?: '';
        $bodyPlain = $request->input('body-plain') ?: '';
        $body      = $bodyHtml ?: nl2br(e($bodyPlain));

        $messageId = $this->cleanMessageId($request->input('Message-Id', ''));
        $inReplyTo = $this->cleanMessageId($request->input('In-Reply-To', ''));

        // ── Auto-link to account ──────────────────────────────────────────
        $accountId = EmailLog::resolveAccountId($fromEmail);

        // ── Process attachments ───────────────────────────────────────────
        $attachments = [];
        $count       = (int) $request->input('attachment-count', 0);

        for ($i = 1; $i <= $count; $i++) {
            $file = $request->file("attachment-{$i}");
            if ($file) {
                $path = $file->store('email-attachments', 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'path' => $path,
                ];
            }
        }

        EmailLog::create([
            'direction'       => 'inbound',
            'from_email'      => $fromEmail,
            'from_name'       => $fromName ?: null,
            'account_id'      => $accountId,
            'recipient_email' => $recipient,
            'subject'         => $subject,
            'body'            => $body,
            'status'          => EmailLog::STATUS_RECEIVED,
            'message_id'      => $messageId ?: null,
            'mailgun_id'      => $token     ?: null,
            'in_reply_to'     => $inReplyTo ?: null,
            'attachments'     => $attachments ?: null,
            // Exclude body fields from payload to keep storage lean
            'raw_payload'     => $request->except(['body-html', 'body-plain', 'body-mime']),
        ]);

        // Mailgun expects HTTP 200 within 25 seconds, otherwise retries
        return response()->json(['status' => 'ok'], 200);
    }

    // ─── Delivery / tracking events ────────────────────────────────────────

    /**
     * Mailgun delivery / tracking events.
     *
     * Mailgun posts JSON:
     * {
     *   "signature": { "timestamp": "...", "token": "...", "signature": "..." },
     *   "event-data": {
     *     "event": "delivered",
     *     "id": "...",
     *     "recipient": "...",
     *     "message": { "headers": { "message-id": "..." } }
     *   }
     * }
     */
    public function events(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if (empty($payload['signature']) || empty($payload['event-data'])) {
            return response()->json(['error' => 'Malformed payload'], 400);
        }

        $sig = $payload['signature'];

        if (!$this->verifySignature(
            $sig['timestamp'] ?? '',
            $sig['token']     ?? '',
            $sig['signature'] ?? ''
        )) {
            Log::warning('Mailgun events: invalid signature', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $eventData = $payload['event-data'];
        $eventType = $eventData['event']    ?? '';
        $mailgunId = $eventData['id']       ?? null;
        $recipient = $eventData['recipient'] ?? '';
        $messageId = $this->cleanMessageId(
            $eventData['message']['headers']['message-id'] ?? ''
        );

        $log = $this->findOutboundLog($messageId, $mailgunId, $recipient);

        if ($log) {
            $this->applyEvent($log, $eventType);
            if ($mailgunId && !$log->mailgun_id) {
                $log->update(['mailgun_id' => $mailgunId]);
            }
        } else {
            Log::info("Mailgun '{$eventType}' event for untracked message", [
                'message_id' => $messageId,
                'recipient'  => $recipient,
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    // ─── Private helpers ───────────────────────────────────────────────────

    /**
     * Verify the Mailgun webhook HMAC-SHA256 signature.
     * @see https://documentation.mailgun.com/docs/mailgun/user-manual/tracking-messages/#securing-webhooks
     */
    private function verifySignature(string $timestamp, string $token, string $signature): bool
    {
        $signingKey = config('services.mailgun.webhook_signing_key');

        if (!$signingKey) {
            if (app()->isLocal()) {
                return true; // Skip verification in local dev when key not configured
            }
            Log::error('MAILGUN_WEBHOOK_SIGNING_KEY is not configured in services.php');
            return false;
        }

        $computed = hash_hmac('sha256', $timestamp . $token, $signingKey);

        return hash_equals($computed, $signature);
    }

    /**
     * Find the outbound EmailLog that corresponds to a delivery event.
     * Tries: message_id → mailgun_id → most-recent to recipient.
     */
    private function findOutboundLog(?string $messageId, ?string $mailgunId, string $recipient): ?EmailLog
    {
        if ($messageId) {
            $log = EmailLog::outbound()->where('message_id', $messageId)->first();
            if ($log) return $log;
        }

        if ($mailgunId) {
            $log = EmailLog::outbound()->where('mailgun_id', $mailgunId)->first();
            if ($log) return $log;
        }

        if ($recipient) {
            return EmailLog::outbound()
                ->where('recipient_email', $recipient)
                ->latest()
                ->first();
        }

        return null;
    }

    /**
     * Apply a Mailgun event type to an EmailLog record.
     */
    private function applyEvent(EmailLog $log, string $event): void
    {
        match ($event) {
            'delivered'                          => $log->markAsDelivered(),
            'failed', 'permanent_fail',
            'temporary_fail'                     => $log->markAsFailed($event),
            'complained'                         => $log->update(['status' => EmailLog::STATUS_COMPLAINED]),
            'opened'                             => $log->update([
                'status'    => EmailLog::STATUS_OPENED,
                'opened_at' => $log->opened_at ?? now(),
            ]),
            'clicked'                            => $log->update(['status' => EmailLog::STATUS_CLICKED]),
            'unsubscribed'                       => $log->markAsBounced(),
            default                              => null,
        };
    }

    /**
     * Strip angle brackets from a Message-Id header value.
     * "<abc@mailgun.org>" → "abc@mailgun.org"
     */
    private function cleanMessageId(string $value): string
    {
        return trim(str_replace(['<', '>'], '', trim($value)));
    }

    /**
     * Parse "Display Name <email@example.com>" → [name, email].
     * Falls back to ['', raw] for bare addresses.
     */
    private function parseAddress(string $raw): array
    {
        if (preg_match('/^(.*?)<([^>]+)>/u', $raw, $m)) {
            return [trim($m[1]), strtolower(trim($m[2]))];
        }
        return ['', strtolower(trim($raw))];
    }
}
