<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use OwenIt\Auditing\Contracts\Auditable;
use Carbon\Carbon;

/**
 * MeterReading Model
 *
 * Backwards compatible with production schema:
 * - Handles both 'reading_value' and 'reading' column names
 * - Handles both 'employee_id' and 'reader_id' column names
 * - Supports both old ('manual'/'automatic') and new ('actual'/'estimated') reading types
 *
 * @property int $id
 * @property int $meter_id
 * @property Carbon $reading_date
 * @property float $reading
 * @property int|null $reader_id
 * @property string $reading_type
 * @property float $consumption
 * @property string|null $notes
 * @property string|null $photo_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MeterReading extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'meter_readings';

    protected $fillable = [
        'meter_id',
        'reading_date',
        'reading_value',
        'reader_id',
        'reading_type',
        'consumption',
        'notes',
        'photo_path',
    ];

    protected $casts = [
        'reading_date' => 'date',
        'reading'      => 'decimal:2',
        'consumption'  => 'decimal:2',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /* =========================
     | Accessors & Mutators
     |========================= */

    /**
     * Accessor: 'reading'
     * Handles backwards compatibility with 'reading_value' column name.
     */
    public function getReadingAttribute($value): ?float
    {
        if ($value !== null) {
            return (float) $value;
        }

        if (isset($this->attributes['reading_value'])) {
            return (float) $this->attributes['reading_value'];
        }

        return null;
    }

    /**
     * Mutator: 'reading'
     * Writes to the correct column depending on schema.
     */
    public function setReadingAttribute($value): void
    {
        if (Schema::hasColumn($this->table, 'reading_value') &&
            !Schema::hasColumn($this->table, 'reading')) {
            $this->attributes['reading_value'] = $value;
        } else {
            $this->attributes['reading'] = $value;
        }
    }

    /**
     * Accessor: 'reader_id'
     * Handles backwards compatibility with 'employee_id' column name.
     */
    public function getReaderIdAttribute($value): ?int
    {
        if ($value !== null) {
            return $value;
        }

        if (isset($this->attributes['employee_id'])) {
            return $this->attributes['employee_id'];
        }

        return null;
    }

    /**
     * Mutator: 'reader_id'
     */
    public function setReaderIdAttribute($value): void
    {
        $this->attributes['reader_id'] = $value;

        if (Schema::hasColumn($this->table, 'employee_id')) {
            $this->attributes['employee_id'] = $value;
        }
    }

    /**
     * Accessor: 'reading_type'
     * Maps legacy DB values to current app values.
     */
    public function getReadingTypeAttribute($value): ?string
    {
        return match ($value) {
            'manual'    => 'actual',
            'automatic' => 'estimated',
            default     => $value,
        };
    }

    /**
     * Mutator: 'reading_type'
     * Maps current app values back to legacy DB values before saving.
     */
    public function setReadingTypeAttribute($value): void
    {
        $this->attributes['reading_type'] = match ($value) {
            'actual'    => 'manual',
            'estimated' => 'automatic',
            default     => $value,
        };
    }

    /* =========================
     | Computed Attributes
     |========================= */

    /**
     * Returns the previous MeterReading MODEL for this meter.
     *
     * Use when you need the full model — e.g. to pass into MeterReadingResource::make().
     */
    public function previousReading(): ?self
    {
        return self::where('meter_id', $this->meter_id)
            ->where('reading_date', '<', $this->reading_date)
            ->orderBy('reading_date', 'desc')
            ->first();
    }

    /**
     * Returns only the numeric value of the previous reading.
     *
     * Use for consumption calculations only.
     * WARNING: Do NOT pass this into MeterReadingResource::make() — it is a float, not a model.
     */
    public function getPreviousReadingValueAttribute(): ?float
    {
        return $this->previousReading()?->reading;
    }

    /**
     * Get the date of the previous reading.
     */
    public function getPreviousReadingDate(): ?Carbon
    {
        return $this->previousReading()?->reading_date;
    }

    /* =========================
     | Relationships
     |========================= */

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reader_id');
    }

    /* =========================
     | Scopes
     |========================= */

    public function scopeActual($query)
    {
        return $query->whereIn('reading_type', ['actual', 'manual']);
    }

    public function scopeEstimated($query)
    {
        return $query->whereIn('reading_type', ['estimated', 'automatic']);
    }

    public function scopeForPeriod($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('reading_date', [$start, $end]);
    }

    public function scopeForMeter($query, int $meterId)
    {
        return $query->where('meter_id', $meterId);
    }

    /* =========================
     | Business Logic
     |========================= */

    public function calculateConsumption(): float
    {
        $previousValue = $this->getPreviousReadingValueAttribute();

        if ($previousValue === null) {
            return 0;
        }

        return max(0, $this->reading - $previousValue);
    }

    /* =========================
     | Boot
     |========================= */

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($reading) {
            if ($reading->isDirty('reading') || $reading->consumption === null) {
                $reading->consumption = $reading->calculateConsumption();
            }
        });
    }
}