<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\GenerateMonthlyBills;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(GenerateMonthlyBills::class)
    ->monthlyOn(1, '02:00')
    ->name('generate:monthly-bills')
    ->withoutOverlapping();

Schedule::job(new \App\Jobs\SendMonthlyStatements())
    ->monthlyOn(1, '02:00')
    ->name('send:monthly-statements')
    ->withoutOverlapping();