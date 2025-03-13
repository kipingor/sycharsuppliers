<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'customer_id',
        'report_type',
        'generated_at',
        'file_path',
        'status',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    /**
     * Get the customer associated with the report.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Check if the report is available for download.
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Format the generated date.
     */
    public function formattedDate(): string
    {
        return Carbon::parse($this->generated_at)->format('Y-m-d H:i:s');
    }
}
