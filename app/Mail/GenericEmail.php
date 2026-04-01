<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Generic outbound email used by EmailService for all manual sends.
 *
 * Supports threading via In-Reply-To / References headers so replies
 * appear as conversations in Gmail, Outlook, etc.
 */
class GenericEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected string  $emailSubject,
        protected string  $emailBody,          // HTML accepted
        protected ?string $inReplyTo = null,   // Message-Id of the email being replied to
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function withSymfonyMessage($message): void
    {
        if ($this->inReplyTo) {
            $ref = '<' . $this->inReplyTo . '>';
            $message->getHeaders()->addTextHeader('In-Reply-To', $ref);
            $message->getHeaders()->addTextHeader('References', $ref);
        }
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->wrapBody($this->emailBody));
    }

    public function attachments(): array
    {
        return [];
    }

    private function wrapBody(string $body): string
    {
        $appName  = config('app.name', 'Water Billing');
        $fromName = config('mail.from.name', $appName);
        $year     = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; background: #f4f4f5; }
    .wrapper { max-width: 600px; margin: 24px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
    .header { background: #1d4ed8; color: #fff; padding: 20px 28px; font-size: 16px; font-weight: 700; letter-spacing: .3px; }
    .body { padding: 28px; line-height: 1.7; }
    .footer { padding: 16px 28px; background: #f9fafb; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">{$fromName}</div>
    <div class="body">{$body}</div>
    <div class="footer">&copy; {$year} {$fromName}. If you did not expect this email, please disregard it.</div>
  </div>
</body>
</html>
HTML;
    }
}
