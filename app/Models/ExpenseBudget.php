<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'monthly_limit',
        'year',
        'month',
        'active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'monthly_limit' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * How much has been spent (approved expenses) in this budget's period.
     */
    public function spentAmount(): float
    {
        return (float) Expense::where('category', $this->category)
            ->where('status', true)
            ->whereYear('expense_date', $this->year)
            ->when($this->month > 0, fn ($q) => $q->whereMonth('expense_date', $this->month))
            ->sum('amount');
    }

    /**
     * Remaining budget (can be negative if over budget).
     */
    public function remainingAmount(): float
    {
        return (float) $this->monthly_limit - $this->spentAmount();
    }

    /**
     * Percentage used (0–100+).
     */
    public function percentUsed(): float
    {
        if ($this->monthly_limit <= 0) {
            return 0;
        }
        return round(($this->spentAmount() / (float) $this->monthly_limit) * 100, 1);
    }

    public function isOverBudget(): bool
    {
        return $this->remainingAmount() < 0;
    }

    /**
     * Find the active budget for a category in the given month/year.
     */
    public static function findForPeriod(string $category, int $year, int $month): ?self
    {
        return static::where('category', $category)
            ->where('active', true)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }
}
