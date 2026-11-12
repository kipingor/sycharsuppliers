<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternalAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly ?int $priorityMessageId = null,
        private readonly ?int $chatMessageId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return array_filter([
            'title' => $this->title,
            'body' => $this->body,
            'priority_message_id' => $this->priorityMessageId,
            'chat_message_id' => $this->chatMessageId,
        ], fn ($value) => $value !== null);
    }
}
