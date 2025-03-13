<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class MeterReading extends Model
{
    /** @use HasFactory<\Database\Factories\MeterReadingFactory> */
    use HasFactory;

    protected $fillable = [
        'meter_id',
        'employee_id',
        'reading_value',
        'reading_date',
    ];

    protected $casts = [
        'reading_date' => 'date',
    ];

    /**
     * Get the meter associated with this reading.
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /**
     * Format the reading date.
     */
    public function formattedDate(): string
    {
        return Carbon::parse($this->reading_date)->format('Y-m-d');
    }
}
