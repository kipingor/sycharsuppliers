<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'billing_id',
        'payment_date',
        'amount',
        'method',
        'transaction_id',
        'status',        
    ];

    protected $casts = [
        'payment_date' => 'date',
    ];

    /**
     * Get the customer associated with this payment.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the bill associated with this payment.
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    /**
     * Check if the payment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Format the payment date.
     */
    public function formattedDate(): string
    {
        return Carbon::parse($this->payment_date)->format('Y-m-d');
    }
}
