<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeterReadingRequest;
use App\Http\Requests\UpdateMeterReadingRequest;
use App\Models\MeterReading;
use Inertia\Inertia;
use Inertia\Response;

class MeterReadingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMeterReadingRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(MeterReading $meterReading)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MeterReading $meterReading)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMeterReadingRequest $request, MeterReading $meterReading)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MeterReading $meterReading)
    {
        //
    }
}
