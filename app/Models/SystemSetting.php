<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['group', 'data'];

    protected function casts(): array
    {
        return ['data' => 'encrypted:array'];
    }
}
