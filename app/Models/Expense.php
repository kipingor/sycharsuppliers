<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use HasFactory;

    protected $fillable = [
        'category',
        'amount',
        'description',
        'expense_date',
        'approved_by',
        'status',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'status' => 'boolean', // Approved (1) or Pending (0)
    ];

    /**
     * Get the admin user who approved the expense.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Mark the expense as approved.
     */
    public function approve(int $userId): void
    {
        $this->update([
            'status' => true,
            'approved_by' => $userId,
        ]);
    }

    /**
     * Mark the expense as pending.
     */
    public function markAsPending(): void
    {
        $this->update([
            'status' => false,
            'approved_by' => null,
        ]);
    }

    /**
     * Format the expense date.
     */
    public function formattedDate(): string
    {
        return Carbon::parse($this->expense_date)->format('Y-m-d');
    }
}
