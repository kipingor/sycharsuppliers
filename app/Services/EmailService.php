<?php

namespace App\Services;

use App\Mail\GenericEmail;
use App\Models\Account;
use App\Models\EmailLog;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Send an outbound email and record it in email_logs.
     *
     * @param  string  $recipient   To: email address
     * @param  string  $subject
     * @param  string  $body        HTML body
     * @param  array   $options     Supported keys:
     *                                name        string   recipient display name
     *                                account_id  int      FK to accounts table
     *                                in_reply_to string   Message-Id for threading
     */
    public function send(
        string $recipient,
        string $subject,
        string $body,
        array  $options = []
    ): EmailLog {
        $accountId = $options['account_id']  ?? EmailLog::resolveAccountId($recipient);
        $inReplyTo = $options['in_reply_to'] ?? null;
        $name      = $options['name']        ?? null;

        $log = EmailLog::create([
            'direction'       => 'outbound',
            'from_email'      => config('mail.from.address'),
            'from_name'       => config('mail.from.name'),
            'account_id'      => $accountId,
            'recipient_email' => $recipient,
            'recipient_name'  => $name,
            'subject'         => $subject,
            'body'            => $body,
            'status'          => EmailLog::STATUS_QUEUED,
            'in_reply_to'     => $inReplyTo,
        ]);

        try {
            $sentMessage = Mail::to($recipient, $name)
                ->send(new GenericEmail($subject, $body, $inReplyTo));

            $log->update([
                'status'      => EmailLog::STATUS_SENT,
                'sent_at'     => now(),
                'message_id'  => $this->extractMessageId($sentMessage),
                'provider_id' => $this->extractProviderId($sentMessage),
            ]);
        } catch (\Throwable $e) {
            $log->markAsFailed($e->getMessage());
        }

        return $log;
    }

    /**
     * Send to an account by ID — looks up account->email automatically.
     * Returns null if the account has no email address on record.
     */
    public function sendToAccount(int $accountId, string $subject, string $body, array $options = []): ?EmailLog
    {
        $account = Account::find($accountId);

        if (!$account || !$account->email) {
            return null;
        }

        return $this->send($account->email, $subject, $body, array_merge([
            'name'       => $account->name,
            'account_id' => $accountId,
        ], $options));
    }

    /**
     * Reply to an inbound email (handles Re: subject prefix + threading headers).
     */
    public function replyTo(EmailLog $inbound, string $body, array $options = []): EmailLog
    {
        $subject = str_starts_with($inbound->subject, 'Re: ')
            ? $inbound->subject
            : 'Re: ' . $inbound->subject;

        return $this->send($inbound->from_email, $subject, $body, array_merge([
            'name'        => $inbound->from_name,
            'account_id'  => $inbound->account_id,
            'in_reply_to' => $inbound->message_id,
        ], $options));
    }

    /**
     * Legacy alias — kept for backward compatibility with existing callers.
     * @deprecated Use send() instead.
     */
    public function sendEmail(string $recipient, string $subject, string $body): void
    {
        $this->send($recipient, $subject, $body);
    }

    private function extractProviderId(?SentMessage $sentMessage): ?string
    {
        if (!$sentMessage) {
            return null;
        }

        $headers = $sentMessage->getOriginalMessage()->getHeaders();
        $header = $headers->get('X-Resend-Email-ID');

        return $header?->getBodyAsString() ?: null;
    }

    private function extractMessageId(?SentMessage $sentMessage): ?string
    {
        if (!$sentMessage) {
            return null;
        }

        $messageId = $sentMessage->getOriginalMessage()->getMessageId();

        return $this->cleanMessageId($messageId);
    }

    private function cleanMessageId(?string $messageId): ?string
    {
        if (!$messageId) {
            return null;
        }

        return trim($messageId, " <>\t\n\r\0\x0B");
    }
}
