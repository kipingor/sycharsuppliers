<?php

namespace App\Contracts\Meter;

use App\Models\Meter;
use Carbon\Carbon;

interface ReadingEstimatorContract
{
    public function estimate(Meter $meter, Carbon $period): float;
}