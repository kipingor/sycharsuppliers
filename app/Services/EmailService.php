<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use App\Mail\GenericEmail; // Example Mailable class

class EmailService
{
    public function sendEmail(string $recipient, string $subject, string $body)
    {
        $log = EmailLog::create([
            'recipient_email' => $recipient,
            'subject' => $subject,
            'body' => $body,
        ]);

        try {
            Mail::to($recipient)->send(new GenericEmail($subject, $body));
            $log->markAsSent();
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
        }
    }
}
