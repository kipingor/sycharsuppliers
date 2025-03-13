<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeterController extends Controller
{
    public function index()
    {
        return Meter::with('customer')->paginate(10);
    }

    public function show($id)
    {
        return Meter::with('customer', 'meterReadings')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'meter_number' => 'required|string|unique:meters,meter_number',
            'location' => 'nullable|string',
            'installation_date' => 'nullable|date',
        ]);

        $meter = Meter::create($request->all());

        return response()->json(['message' => 'Meter added successfully', 'meter' => $meter]);
    }

    public function activate($id)
    {
        $meter = Meter::findOrFail($id);
        $meter->activate();

        return response()->json(['message' => 'Meter activated successfully']);
    }

    public function deactivate($id)
    {
        $meter = Meter::findOrFail($id);
        $meter->deactivate();

        return response()->json(['message' => 'Meter deactivated successfully']);
    }
}
