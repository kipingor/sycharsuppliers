<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBills;
use App\Traits\HasPayments;
use App\Traits\HasNotifications;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory, HasBills, HasPayments, HasNotifications;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean', // Active (1) or Inactive (0)
    ];

    /**
     * A customer can have multiple meters.
     */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }    

    /**
     * Activate the customer.
     */
    public function activate(): void
    {
        $this->update(['status' => true]);
    }

    /**
     * Deactivate the customer.
     */
    public function deactivate(): void
    {
        $this->update(['status' => false]);
    }
}
