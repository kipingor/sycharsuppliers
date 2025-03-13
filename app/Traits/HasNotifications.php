<?php

namespace App\Traits;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait HasNotifications
{
    /**
     * Establish a one-to-many relationship with notifications.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Send an email notification.
     */
    public function sendEmailNotification(string $subject, string $message): void
    {
        if (!isset($this->email)) {
            Log::warning("Email not found for user ID: {$this->id}");
            return;
        }

        try {
            Mail::raw($message, function ($mail) use ($subject) {
                $mail->to($this->email)
                    ->subject($subject);
            });

            $this->logNotification('email', $subject, $message);
        } catch (\Exception $e) {
            Log::error("Email notification failed: " . $e->getMessage());
        }
    }

    /**
     * Send an SMS notification.
     */
    public function sendSmsNotification(string $message): void
    {
        if (!isset($this->phone)) {
            Log::warning("Phone number not found for user ID: {$this->id}");
            return;
        }

        try {
            // Replace with actual SMS API integration
            Http::post('https://sms-provider.com/api/send', [
                'phone' => $this->phone,
                'message' => $message,
            ]);

            $this->logNotification('sms', 'SMS Notification', $message);
        } catch (\Exception $e) {
            Log::error("SMS notification failed: " . $e->getMessage());
        }
    }

    /**
     * Send a push notification via Firebase.
     */
    public function sendPushNotification(string $title, string $message): void
    {
        if (!isset($this->device_token)) {
            Log::warning("Device token not found for user ID: {$this->id}");
            return;
        }

        try {
            Http::post('https://fcm.googleapis.com/fcm/send', [
                'to' => $this->device_token,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                ],
            ]);

            $this->logNotification('push', $title, $message);
        } catch (\Exception $e) {
            Log::error("Push notification failed: " . $e->getMessage());
        }
    }

    /**
     * Log the notification into the database.
     */
    private function logNotification(string $type, string $title, string $message): void
    {
        $this->notifications()->create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'status' => 'sent',
        ]);
    }
}
