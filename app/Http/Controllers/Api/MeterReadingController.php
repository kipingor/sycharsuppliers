<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MeterReading;
use App\Models\Meter;

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
        $validatedData = $request->validate([
            'meter_id' => 'required|exists:meters,id',
            'customer_id' => 'required|exists:customers,id',
            'reading' => 'required|integer|min:0',
            'reading_date' => 'required|date',
            'recorded_by' => 'nullable|string',
        ]);

        $reading = MeterReading::create($validatedData);

        return response()->json(['message' => 'Meter reading recorded successfully', 'reading' => $reading], 201);
    }

    public function getLastReading(Meter $meter)
    {
        // $validatedData = $request->validate(['meter_id' => 'required|exists:meters,id']);
        
        $lastReading = MeterReading::where('meter_id', $meter->id)
            ->orderBy('reading_date', 'desc')
            ->first();
        
        return response()->json($lastReading);
    }
}
