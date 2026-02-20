<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeterReadingRequest;
use App\Http\Resources\MeterReadingResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\MeterReading;
use App\Models\Meter;
use App\Services\Meter\MeterReadingService;

class MeterReadingController extends Controller
{
    public function __construct(
        protected MeterReadingService $meterReadingService
    ) {}

    /**
     * Display a listing of meter readings
     */
    public function index(Request $request): JsonResponse
    {
        $query = MeterReading::with(['meter.account', 'reader']);

        // Apply filters
        if ($request->has('meter_id')) {
            $query->where('meter_id', $request->meter_id);
        }

        if ($request->has('reading_type')) {
            $query->where('reading_type', $request->reading_type);
        }

        if ($request->has('from_date')) {
            $query->where('reading_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('reading_date', '<=', $request->to_date);
        }

        $readings = $query->latest('reading_date')->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => MeterReadingResource::collection($readings),
            'meta' => [
                'current_page' => $readings->currentPage(),
                'last_page' => $readings->lastPage(),
                'per_page' => $readings->perPage(),
                'total' => $readings->total(),
            ],
        ]);
    }

    /**
     * Display the specified meter reading
     */
    public function show(MeterReading $meterReading): JsonResponse
    {
        $meterReading->load(['meter.account', 'reader']);

        return response()->json([
            'data' => MeterReadingResource::make($meterReading),
        ]);
    }

    /**
     * Store a newly created meter reading
     */
    public function store(StoreMeterReadingRequest $request): JsonResponse
    {
        try {
            $reading = $this->meterReadingService->createReading($request->validated());

            return response()->json([
                'message' => 'Meter reading recorded successfully',
                'data' => MeterReadingResource::make($reading->load(['meter.account', 'reader'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record meter reading',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update the specified meter reading
     */
    public function update(Request $request, MeterReading $meterReading): JsonResponse
    {
        $validated = $request->validate([
            'reading_date' => 'sometimes|date|before_or_equal:today',
            'reading' => 'sometimes|numeric|min:0',
            'reading_type' => 'sometimes|in:actual,estimated,correction',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->meterReadingService->updateReading($meterReading, $validated);

            return response()->json([
                'message' => 'Reading updated successfully',
                'data' => MeterReadingResource::make($meterReading->fresh(['meter.account', 'reader'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update reading',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified meter reading
     */
    public function destroy(MeterReading $meterReading): JsonResponse
    {
        try {
            $this->meterReadingService->deleteReading($meterReading);

            return response()->json([
                'message' => 'Reading deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete reading',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get the last reading for a specific meter
     */
    public function getLastReading(Meter $meter): JsonResponse
    {
        $lastReading = MeterReading::where('meter_id', $meter->id)
            ->with('reader')
            ->orderBy('reading_date', 'desc')
            ->first();

        if (!$lastReading) {
            return response()->json([
                'data' => null,
                'message' => 'No readings found for this meter',
            ]);
        }

        return response()->json([
            'data' => MeterReadingResource::make($lastReading),
        ]);
    }

    /**
     * Get readings for a specific meter
     */
    public function forMeter(Request $request, Meter $meter): JsonResponse
    {
        $readings = $meter->readings()
            ->with('reader')
            ->latest('reading_date')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => MeterReadingResource::collection($readings),
            'meta' => [
                'current_page' => $readings->currentPage(),
                'last_page' => $readings->lastPage(),
                'per_page' => $readings->perPage(),
                'total' => $readings->total(),
                'meter' => [
                    'id' => $meter->id,
                    'meter_number' => $meter->meter_number,
                    'meter_name' => $meter->meter_name,
                    'latest_reading' => $meter->getLatestReadingValue(),
                    'average_consumption' => $meter->getAverageMonthlyConsumption(),
                ],
            ],
        ]);
    }

    /**
     * Get consumption statistics for a meter
     */
    public function getConsumptionStats(Request $request, Meter $meter): JsonResponse
    {
        $period = $request->input('period', 'month'); // day, week, month, year

        $startDate = match($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        $readings = $meter->readings()
            ->where('reading_date', '>=', $startDate)
            ->orderBy('reading_date')
            ->get();

        $totalConsumption = $readings->sum('consumption');
        $averageConsumption = $readings->avg('consumption');
        $readingsCount = $readings->count();

        return response()->json([
            'data' => [
                'period' => $period,
                'start_date' => $startDate->toDateString(),
                'end_date' => now()->toDateString(),
                'total_consumption' => round($totalConsumption, 2),
                'average_consumption' => round($averageConsumption, 2),
                'readings_count' => $readingsCount,
                'readings' => MeterReadingResource::collection($readings),
            ],
        ]);
    }
}