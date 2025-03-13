<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TaxDocument extends Model
{
    /** @use HasFactory<\Database\Factories\TaxDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'document_type',
        'file_path',
        'status',
        'uploaded_at',
        'approved_by',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    /**
     * Get the customer associated with the tax document.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who approved the document.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if the document is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Mark the document as approved.
     */
    public function approve(int $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
        ]);
    }

    /**
     * Format the uploaded date.
     */
    public function formattedDate(): string
    {
        return Carbon::parse($this->uploaded_at)->format('Y-m-d H:i:s');
    }
}
