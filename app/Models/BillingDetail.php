<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class BillingDetail extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'billing_id',
        'previous_reading_value',
        'current_reading_value',
        'units_used',
    ];

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }
}
