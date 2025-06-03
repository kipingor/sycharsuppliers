<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Notification extends Model
{
    /**
     * Get the user associated with the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the resident associated with the notification.
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * Check if the notification is read.
     */
    public function isRead(): bool
    {
        return $this->status === 'read';
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        $this->update(['status' => 'read']);
    }

    /**
     * Format the sent date.
     */
    public function formattedDate(): string
    {
        return Carbon::parse($this->sent_at)->format('Y-m-d H:i:s');
    }
}
