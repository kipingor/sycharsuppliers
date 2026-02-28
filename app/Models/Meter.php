<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Meter Model
 * 
 * Represents a water meter. Supports both individual meters and bulk meters.
 * Bulk meters can have sub-meters for consumption distribution.
 * 
 * @property int $id
 * @property int $account_id
 * @property string $meter_number
 * @property string $meter_name
 * @property string $type (water|sewer)
 * @property string $meter_type (individual|bulk)
 * @property int|null $parent_meter_id
 * @property float|null $allocation_percentage
 * @property string $status (active|inactive|faulty)
 * @property \Carbon\Carbon|null $installed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @property-read Account $account
 * @property-read Meter|null $parentMeter
 * @property-read \Illuminate\Database\Eloquent\Collection|Meter[] $subMeters
 * @property-read \Illuminate\Database\Eloquent\Collection|MeterReading[] $readings
 * @property-read \Illuminate\Database\Eloquent\Collection|BillingDetail[] $billingDetails
 */
class Meter extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'account_id',
        'resident_id',
        'meter_number',
        'meter_name',
        'type',
        'meter_type',
        'parent_meter_id',
        'allocation_percentage',
        'status',
        'installed_at',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'allocation_percentage' => 'decimal:2',
    ];

    /**
     * Attributes to include in audit
     */
    protected $auditInclude = [
        'account_id',
        'resident_id',
        'meter_number',
        'meter_name',
        'type',
        'meter_type',
        'parent_meter_id',
        'allocation_percentage',
        'status',
    ];

    /**
     * Generate audit tags
     */
    public function generateTags(): array
    {
        return [
            'meter',
            'resident:' . $this->resident_id,
            'account:' . $this->account_id,
            'type:' . $this->type,
            'meter_type:' . $this->meter_type,
            'status:' . $this->status,
        ];
    }

    /* =========================
     | Relationships
     |========================= */

    /**
     * Get the account this meter belongs to
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * Get the parent meter (for sub-meters)
     */
    public function parentMeter(): BelongsTo
    {
        return $this->belongsTo(Meter::class, 'parent_meter_id');
    }

    /**
     * Get all sub-meters (for bulk meters)
     */
    public function subMeters(): HasMany
    {
        return $this->hasMany(Meter::class, 'parent_meter_id');
    }

    /**
     * Get all readings for this meter
     */
    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /**
     * Get all billing details for this meter
     */
    public function billingDetails(): HasMany
    {
        return $this->hasMany(BillingDetail::class, 'meter_id');
    }

    /**
     * Get all billings that include this meter (many-to-many through billing_details)
     * Useful for accessing billing headers and status
     */
    public function billings(): BelongsToMany
    {
        return $this->belongsToMany(
            Billing::class,
            'billing_details',
            'meter_id',
            'billing_id'
        )->withPivot([
            'id',
            'previous_reading_value',
            'current_reading_value',
            'units_used',
            'rate',
            'amount',
            'description'
        ])->withTimestamps();
    }

    public function bulkMeter()
    {
        return $this->belongsTo(Meter::class, 'bulk_meter_id');
    }

    // Add after existing relationships (around line 140)

    /**
     * Get billings associated with this meter
     * Note: Billings belong to account, not meter directly
     */
    public function getBillingsForMeter()
    {
        if (!$this->account_id) {
            return collect([]);
        }

        return Billing::where('account_id', $this->account_id)
            ->whereHas('details', function ($query) {
                $query->where('meter_id', $this->id);
            })
            ->orderBy('billing_period', 'desc')
            ->get();
    }

    /**
     * DEPRECATED: Legacy support
     * @deprecated Use account->billings() instead
     */
    public function bills()
    {
        if (!$this->relationLoaded('account')) {
            $this->load('account');
        }

        if (!$this->account) {
            // Return empty query builder, not collection
            return Billing::query()->whereRaw('1 = 0');
        }

        return $this->account->billings()
            ->whereHas('details', function ($query) {
                $query->where('meter_id', $this->id);
            });
    }

    /**
     * DEPRECATED: Legacy support
     * @deprecated Use account->payments() instead
     */
    public function payments()
    {
        if (!$this->relationLoaded('account')) {
            $this->load('account');
        }

        if (!$this->account) {
            return Payment::query()->whereRaw('1 = 0');
        }

        return $this->account->payments();
    }

    /**
     * Alias for readings() to match controller usage
     */
    public function meterReadings(): HasMany
    {
        return $this->readings();
    }

    /* =========================
     | Scopes
     |========================= */

    /**
     * Scope to get active meters
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get inactive meters
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope to get faulty meters
     */
    public function scopeFaulty($query)
    {
        return $query->where('status', 'faulty');
    }

    /**
     * Scope to get bulk meters
     */
    public function scopeBulk($query)
    {
        return $query->where('meter_type', 'bulk');
    }

    /**
     * Scope to get individual meters
     */
    public function scopeIndividual($query)
    {
        return $query->where('meter_type', 'individual');
    }

    /**
     * Scope to get sub-meters only
     */
    public function scopeSubMeters($query)
    {
        return $query->whereNotNull('parent_meter_id');
    }

    /**
     * Scope to get parent meters only
     */
    public function scopeParentMeters($query)
    {
        return $query->whereNull('parent_meter_id');
    }

    /**
     * Scope to get meters for a specific account
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /* =========================
     | Helper Methods
     |========================= */

    /**
     * Check if this is a bulk meter
     */
    public function isBulkMeter(): bool
    {
        return $this->meter_type === 'bulk';
    }

    /**
     * Check if this is an individual meter
     */
    public function isIndividualMeter(): bool
    {
        return $this->meter_type === 'individual';
    }

    /**
     * Check if this is a sub-meter
     */
    public function isSubMeter(): bool
    {
        return $this->parent_meter_id !== null;
    }

    /**
     * Check if meter is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if meter is faulty
     */
    public function isFaulty(): bool
    {
        return $this->status === 'faulty';
    }

    /**
     * Get the latest meter reading
     */
    public function getLatestReading(): ?MeterReading
    {
        return $this->readings()->latest('reading_date')->first();
    }

    /**
     * Get the latest reading value
     */
    public function getLatestReadingValue(): ?float
    {
        $reading = $this->getLatestReading();
        return $reading ? $reading->reading : null;
    }

    /**
     * Get average monthly consumption
     * 
     * @param int $months Number of months to calculate average
     * @return float
     */
    public function getAverageMonthlyConsumption(int $months = 3): float
    {
        $readings = $this->readings()
            ->where('reading_date', '>=', now()->subMonths($months))
            ->orderBy('reading_date', 'desc')
            ->get();

        if ($readings->count() < 2) {
            return 0;
        }

        $totalConsumption = 0;
        $periods = 0;

        for ($i = 0; $i < $readings->count() - 1; $i++) {
            $current = $readings[$i];
            $previous = $readings[$i + 1];

            $consumption = $current->reading - $previous->reading;
            if ($consumption > 0) {
                $totalConsumption += $consumption;
                $periods++;
            }
        }

        return $periods > 0 ? $totalConsumption / $periods : 0;
    }

    /**
     * Calculate consumption between two readings
     * 
     * @param MeterReading $currentReading
     * @param MeterReading $previousReading
     * @return float
     */
    public function calculateConsumption(MeterReading $currentReading, MeterReading $previousReading): float
    {
        return max(0, $currentReading->reading - $previousReading->reading);
    }

    /**
     * Check if meter has sub-meters
     */
    public function hasSubMeters(): bool
    {
        return $this->isBulkMeter() && $this->subMeters()->exists();
    }

    /**
     * Get total allocation percentage of sub-meters
     * 
     * @return float
     */
    public function getTotalSubMeterAllocation(): float
    {
        if (!$this->isBulkMeter()) {
            return 0;
        }

        return $this->subMeters()->sum('allocation_percentage');
    }

    /**
     * Check if sub-meter allocations total 100%
     * 
     * @return bool
     */
    public function hasCompleteAllocation(): bool
    {
        if (!$this->isBulkMeter()) {
            return true;
        }

        $total = $this->getTotalSubMeterAllocation();
        return abs($total - 100) < 0.01; // Allow for rounding
    }

    /**
     * Get unallocated percentage
     * 
     * @return float
     */
    public function getUnallocatedPercentage(): float
    {
        if (!$this->isBulkMeter()) {
            return 0;
        }

        return max(0, 100 - $this->getTotalSubMeterAllocation());
    }

    /**
     * Activate the meter
     */
    public function activate(): bool
    {
        return $this->update(['status' => 'active']);
    }

    /**
     * Deactivate the meter
     */
    public function deactivate(): bool
    {
        return $this->update(['status' => 'inactive']);
    }

    /**
     * Mark meter as faulty
     */
    public function markAsFaulty(): bool
    {
        return $this->update(['status' => 'faulty']);
    }


    public function getCurrentReading()
    {
        return $this->readings()->latest('reading_date')->value('reading_value');
    }

    public function getTotalConsumption()
    {
        $currentReading = $this->getCurrentReading();
        return $currentReading ? ($currentReading - $this->initial_reading) : 0;
    }

    public function getLastReadingDate()
    {
        return $this->readings()->latest('reading_date')->value('reading_date');
    }

    /**
     * Get billings for this meter (through account)
     */
    public function getBillings()
    {
        return $this->account->billings()
            ->whereHas('details', function ($query) {
                $query->where('meter_id', $this->id);
            });
    }

    /**
     * Get payments for this meter's account
     */
    public function getPayments()
    {
        return $this->account->payments();
    }

    /**
     * Get meter summary
     * 
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'meter_number' => $this->meter_number,
            'meter_name' => $this->meter_name,
            'type' => $this->type,
            'meter_type' => $this->meter_type,
            'status' => $this->status,
            'is_bulk' => $this->isBulkMeter(),
            'is_sub_meter' => $this->isSubMeter(),
            'parent_meter_id' => $this->parent_meter_id,
            'allocation_percentage' => $this->allocation_percentage,
            'sub_meter_count' => $this->isBulkMeter() ? $this->subMeters()->count() : 0,
            'total_allocation' => $this->getTotalSubMeterAllocation(),
            'has_complete_allocation' => $this->hasCompleteAllocation(),
            'latest_reading' => $this->getLatestReadingValue(),
            'average_consumption' => $this->getAverageMonthlyConsumption(),
            'reading_count' => $this->readings()->count(),
            'installed_at' => $this->installed_at?->toDateString(),
        ];
    }

    /**
     * Check if a reading value has been used in any billing
     * 
     * @param float $readingValue
     * @return bool
     */
    public function readingHasBeenBilled(float $readingValue): bool
    {
        return $this->billingDetails()
            ->where(function ($query) use ($readingValue) {
                $query->where('previous_reading_value', $readingValue)
                      ->orWhere('current_reading_value', $readingValue);
            })
            ->exists();
    }

    /**
     * Get total billed amount for this meter
     * 
     * @return float
     */
    public function getTotalBilledAmount(): float
    {
        return (float) $this->billingDetails()->sum('amount');
    }

    /**
     * Get billing detail for a specific period
     * 
     * @param string $period Format: YYYY-MM
     * @return BillingDetail|null
     */
    public function getBillingDetailForPeriod(string $period): ?BillingDetail
    {
        return $this->billingDetails()
            ->whereHas('billing', function($q) use ($period) {
                $q->where('billing_period', $period);
            })
            ->with('billing')
            ->first();
    }
}