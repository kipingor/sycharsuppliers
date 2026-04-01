<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasFactory;

    // ─── Status constants ──────────────────────────────────────────────────
    const STATUS_QUEUED     = 'queued';
    const STATUS_SENT       = 'sent';
    const STATUS_DELIVERED  = 'delivered';
    const STATUS_OPENED     = 'opened';
    const STATUS_CLICKED    = 'clicked';
    const STATUS_BOUNCED    = 'bounced';
    const STATUS_COMPLAINED = 'complained';
    const STATUS_FAILED     = 'failed';
    const STATUS_RECEIVED   = 'received';
    const STATUS_READ       = 'read';

    protected $fillable = [
        'direction', 'from_email', 'from_name', 'account_id',
        'recipient_email', 'recipient_name', 'subject', 'body',
        'status', 'error_message',
        'message_id', 'mailgun_id', 'in_reply_to',
        'attachments', 'raw_payload',
        'sent_at', 'read_at', 'delivered_at', 'opened_at', 'bounced_at',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'read_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at'    => 'datetime',
        'bounced_at'   => 'datetime',
        'attachments'  => 'array',
        'raw_payload'  => 'array',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // ─── Query scopes ──────────────────────────────────────────────────────

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }
    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }
    public function scopeUnread($query)
    {
        return $query->inbound()->whereNull('read_at');
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    // ─── Boolean helpers ───────────────────────────────────────────────────

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }
    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    // ─── State transitions ─────────────────────────────────────────────────

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now(), 'status' => self::STATUS_READ]);
        }
    }

    public function markAsSent(): void
    {
        $this->update(['status' => self::STATUS_SENT, 'sent_at' => now()]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update(['status' => self::STATUS_FAILED, 'error_message' => $errorMessage]);
    }

    public function markAsDelivered(): void
    {
        $this->update(['status' => self::STATUS_DELIVERED, 'delivered_at' => now()]);
    }

    public function markAsBounced(): void
    {
        $this->update(['status' => self::STATUS_BOUNCED, 'bounced_at' => now()]);
    }

    // ─── Account resolution ────────────────────────────────────────────────

    /**
     * Given an email address, return the matching Account ID (if any).
     * Used by the inbound webhook to auto-link incoming emails.
     */
    public static function resolveAccountId(string $email): ?int
    {
        return Account::where('email', strtolower(trim($email)))->value('id');
    }

    // ─── Threading ─────────────────────────────────────────────────────────

    /**
     * Return all emails in the same conversation thread.
     */
    public function threadEmails()
    {
        $root = $this->in_reply_to ?? $this->message_id;

        if (!$root) {
            return collect([$this]);
        }

        return static::where(function ($q) use ($root) {
            $q->where('message_id', $root)
              ->orWhere('in_reply_to', $root);
        })
        ->orWhere('id', $this->id)
        ->orderBy('created_at')
        ->get();
    }
}
