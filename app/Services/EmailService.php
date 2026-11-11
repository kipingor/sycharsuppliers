<?php

namespace App\Services;

use App\Mail\GenericEmail;
use App\Models\Account;
use App\Models\EmailLog;
use Illuminate\Mail\Mailable;
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
     *                                attachments array    [{name, mime, content}]
     *                                from_address string Sender identity
     *                                sender_user_id int   User who sent the message
     *                                thread_id   int      Conversation root
     */
    public function send(
        string $recipient,
        string $subject,
        string $body,
        array  $options = []
    ): EmailLog {
        $inReplyTo = $options['in_reply_to'] ?? null;
        $attachments = $options['attachments'] ?? [];

        return $this->sendMailable(
            $recipient,
            $subject,
            $body,
            new GenericEmail($subject, $body, $inReplyTo, $attachments),
            $options,
        );
    }

    /**
     * Send and track an email backed by a purpose-built mailable.
     */
    public function sendMailable(
        string $recipient,
        string $subject,
        string $body,
        Mailable $mailable,
        array $options = [],
    ): EmailLog {
        $accountId = $options['account_id'] ?? EmailLog::resolveAccountId($recipient);
        $name = $options['name'] ?? null;
        $attachments = $options['attachments'] ?? [];
        $fromAddress = $options['from_address'] ?? config('mail.from.address');
        $fromName = $options['from_name'] ?? config('mail.from.name');

        $log = EmailLog::create([
            'direction'       => 'outbound',
            'from_email'      => $fromAddress,
            'from_name'       => $fromName,
            'account_id'      => $accountId,
            'recipient_email' => $recipient,
            'recipient_name'  => $name,
            'subject'         => $subject,
            'body'            => $body,
            'billing_id'      => $options['billing_id'] ?? null,
            'sender_user_id'  => $options['sender_user_id'] ?? auth()->id(),
            'thread_id'       => $options['thread_id'] ?? null,
            'status'          => EmailLog::STATUS_QUEUED,
            'in_reply_to'     => $options['in_reply_to'] ?? null,
            'attachments'     => array_map(
                fn (array $attachment) => [
                    'name' => $attachment['name'],
                    'mime' => $attachment['mime'] ?? 'application/octet-stream',
                    'size' => isset($attachment['content']) ? strlen($attachment['content']) : ($attachment['size'] ?? null),
                ],
                $attachments
            ) ?: null,
        ]);
        if (!$log->thread_id) {
            $log->update(['thread_id' => $log->id]);
        }

        try {
            if ($mailable instanceof GenericEmail) {
                $mailable->setSender($fromAddress, $fromName);
            }
            $sentMessage = Mail::to($recipient, $name)->send($mailable);
        } catch (\Throwable $e) {
            $log->markAsFailed($e->getMessage());

            return $log;
        }

        $log->update([
            'status'      => EmailLog::STATUS_SENT,
            'sent_at'     => now(),
            'message_id'  => $this->extractMessageId($sentMessage),
            'provider_id' => $this->extractProviderId($sentMessage),
        ]);

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
            'from_address' => $inbound->recipient_email,
            'thread_id' => $inbound->conversationRoot()->id,
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

        return $this->cleanMessageId(
            $sentMessage->getSymfonySentMessage()->getMessageId()
        );
    }

    private function cleanMessageId(?string $messageId): ?string
    {
        if (!$messageId) {
            return null;
        }

        return trim($messageId, " <>\t\n\r\0\x0B");
    }
}
