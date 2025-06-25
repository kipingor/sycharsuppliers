<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MeterReadingController;
use App\Http\Controllers\Api\MeterController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/meter-readings/last/{meter}', [MeterReadingController::class, 'getLastReading']);
Route::get('/meters/{meter}/bills-payments', [MeterController::class, 'billPayments']);
Route::post('/meters/{meter}/send-statement', [MeterController::class, 'sendStatement']);
Route::get('/meters/reading-list', [MeterController::class, 'readingList']);
Route::get('/meters/download-statements', [MeterController::class, 'downloadAllStatements']);

