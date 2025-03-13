<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeterReadingController extends Controller
{
    public function index()
    {
        return MeterReading::with('meter', 'customer')->paginate(10);
    }

    public function show($id)
    {
        return MeterReading::with('meter', 'customer')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'meter_id' => 'required|exists:meters,id',
            'customer_id' => 'required|exists:customers,id',
            'reading' => 'required|integer|min:0',
            'reading_date' => 'required|date',
            'recorded_by' => 'nullable|string',
        ]);

        $reading = MeterReading::create($request->all());

        return response()->json(['message' => 'Meter reading recorded successfully', 'reading' => $reading]);
    }
}
