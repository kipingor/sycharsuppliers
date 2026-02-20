<?php

namespace App\Services\Meter;

use App\Contracts\Meter\ReadingEstimatorContract;
use App\Models\Meter;
use Carbon\Carbon;

class EstimatedReadingService implements ReadingEstimatorContract
{
    public function estimate(Meter $meter, Carbon $period): float
    {
        $average = $meter->readings()
            ->orderByDesc('reading_date')
            ->take(3)
            ->get()
            ->avg(fn ($r) => $r->units_used);

        return round($average ?? 0, 2);
    }
}