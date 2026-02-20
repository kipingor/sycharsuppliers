<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'idnumber',
        'position',
        'salary',
        'status',
        'hire_date',
    ];

    protected $casts = [
        'hire_date' => 'date',
    ];

    /**
     * Get the user account associated with the employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Activate the employee.
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Deactivate the employee.
     */
    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }
}
