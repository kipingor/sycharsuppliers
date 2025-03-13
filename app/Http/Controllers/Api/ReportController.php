<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return Report::with('customer')->paginate(10);
    }

    public function show($id)
    {
        return Report::with('customer')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'report_type' => 'required|string',
        ]);

        $report = Report::create([
            'customer_id' => $request->customer_id,
            'report_type' => $request->report_type,
            'status' => 'pending',
            'generated_at' => now(),
        ]);

        return response()->json(['message' => 'Report request created successfully', 'report' => $report]);
    }

    public function markAsAvailable($id, Request $request)
    {
        $request->validate(['file_path' => 'required|string']);

        $report = Report::findOrFail($id);
        $report->update([
            'status' => 'available',
            'file_path' => $request->file_path,
            'generated_at' => now(),
        ]);

        return response()->json(['message' => 'Report marked as available']);
    }

    public function download($id)
    {
        $report = Report::findOrFail($id);

        if (!$report->isAvailable()) {
            return response()->json(['message' => 'Report is not available'], 400);
        }

        return response()->download(storage_path("app/{$report->file_path}"));
    }
}
