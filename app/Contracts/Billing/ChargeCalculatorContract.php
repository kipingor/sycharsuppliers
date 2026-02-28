<?php

namespace App\Contracts\Billing;

use App\Models\Meter;
use Carbon\Carbon;

interface ChargeCalculatorContract
{
    public function calculate(Meter $meter, Carbon $period): ChargeResult;
}