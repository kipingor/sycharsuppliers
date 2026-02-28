<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeterReadingRequest;
use App\Http\Requests\UpdateMeterReadingRequest;
use App\Http\Resources\MeterReadingResource;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Services\Billing\BulkMeterService;
use App\Services\Meter\MeterReadingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Meter Reading Controller (REFACTORED)
 *
 * Follows strict thin controller pattern:
 * - Authorization
 * - Input validation
 * - Service delegation
 * - Response formatting
 *
 * NO BUSINESS LOGIC ALLOWED
 *
 * @package App\Http\Controllers
 */
class MeterReadingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MeterReadingService $meterReadingService,
        protected BulkMeterService $bulkMeterService
    ) {}

    /**
     * Display a listing of meter readings
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MeterReading::class);

        $readings = MeterReading::query()
            ->with(['meter.account', 'reader'])
            ->when(
                $request->search,
                fn($q, $search) =>
                $q->whereHas(
                    'meter',
                    fn($q) =>
                    $q->where('meter_number', 'like', "%{$search}%")
                        ->orWhere('meter_name', 'like', "%{$search}%")
                )
            )
            ->when(
                $request->meter_id,
                fn($q, $id) =>
                $q->where('meter_id', $id)
            )
            ->when(
                $request->reading_type,
                fn($q, $type) =>
                $q->where('reading_type', $type)
            )
            ->when(
                $request->from_date,
                fn($q, $date) =>
                $q->where('reading_date', '>=', $date)
            )
            ->when(
                $request->to_date,
                fn($q, $date) =>
                $q->where('reading_date', '<=', $date)
            )
            ->latest('reading_date')
            ->paginate(15)
            ->withQueryString();

        // Transform using Resource
        $readings->through(fn($reading) => MeterReadingResource::make($reading)->resolve());

        return Inertia::render('meter-readings/index', [
            'readings' => $readings,
            'filters' => $request->only(['search', 'meter_id', 'reading_type', 'from_date', 'to_date']),
            'meters' => Meter::active()
                ->select('id', 'meter_number', 'meter_name')
                ->orderBy('meter_number')
                ->get(),
            'can' => [
                'create' => Auth::user()->can('create', MeterReading::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new meter reading
     */
    public function create(): Response
    {
        $this->authorize('create', MeterReading::class);

        $currentYear = now()->year;
        $currentMonthNum = now()->month;

        $meters = Meter::active()
            ->whereDoesntHave('readings', function ($query) use ($currentYear, $currentMonthNum) {
                $query->whereYear('reading_date', $currentYear)
                    ->whereMonth('reading_date', $currentMonthNum);
            })
            ->select('id', 'meter_number', 'meter_name')
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'meter_number' => $m->meter_number,
                'meter_name' => $m->meter_name,
                'current_reading' => $m->getLatestReadingValue(),
                'last_reading_date' => $m->getLastReadingDate()?->format('Y-m-d'),
            ]);

        return Inertia::render('meter-readings/create', [
            'meters' => $meters,
            'current_month' => now()->format('Y-m'),
            'total_meters' => Meter::active()->count(),
            'unread_meters' => $meters->count(),
            'can' => [
                'create' => Auth::user()->can('create', MeterReading::class),
            ],
        ]);
    }

    /**
     * Store a newly created meter reading
     *
     * ALL BUSINESS LOGIC DELEGATED TO SERVICE
     */
    public function store(StoreMeterReadingRequest $request): RedirectResponse
    {
        try {
            // Delegate to service - ALL validation happens there
            $reading = $this->meterReadingService->createReading($request->validated());

            // Check if bulk meter and offer distribution
            if ($reading->meter->isBulkMeter() && $reading->reading_type === 'actual') {
                return redirect()->route('meter-readings.show', $reading)
                    ->with('success', 'Reading recorded successfully')
                    ->with('info', 'This is a bulk meter reading. You can distribute it to sub-meters.');
            }

            return redirect()->route('meter-readings.show', $reading)
                ->with('success', 'Reading recorded successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified meter reading
     */
    public function show(MeterReading $meterReading): Response
    {
        $this->authorize('view', $meterReading);

        $meterReading->load([
            'meter.account',
            'reader',
        ]);

        $data = [
            'reading' => MeterReadingResource::make($meterReading)->resolve(),
        ];

        // If bulk meter, check distribution status
        if ($meterReading->meter->isBulkMeter()) {
            $data['can_distribute'] = !$meterReading->is_distributed
                && $meterReading->meter->hasSubMeters()
                && $meterReading->reading_type === 'actual';

            if ($meterReading->is_distributed) {
                $data['distributed_readings'] = MeterReading::where('parent_reading_id', $meterReading->id)
                    ->with('meter')
                    ->get()
                    ->map(fn($r) => MeterReadingResource::make($r)->resolve());
            }
        }

        // Get previous reading model for comparison
        // NOTE: previous_reading_value is a float accessor — use previousReading() for the full model
        $previousReading = $meterReading->previousReading();
        if ($previousReading) {
            $data['previous_reading_value'] = MeterReadingResource::make($previousReading)->resolve();
        }

        $data['can'] = [
            'update' => Auth::user()->can('update', $meterReading),
            'delete' => Auth::user()->can('delete', $meterReading),
            'distribute' => Auth::user()->can('distribute', $meterReading),
        ];

        return Inertia::render('meter-readings/show', $data);
    }

    /**
     * Show the form for editing the specified meter reading
     */
    public function edit(MeterReading $meterReading): Response
    {
        $this->authorize('update', $meterReading);

        return Inertia::render('meter-readings/edit', [
            'reading' => MeterReadingResource::make($meterReading->load('meter.account'))->resolve(),
            // Get meters for this account
            'meters' => Meter::where('account_id', $meterReading->meter->account_id)
                ->orderBy('meter_number')
                ->get(['id', 'meter_number', 'meter_name'])
                ->map(fn($m) => [
                    'id' => $m->id,
                    'meter_number' => $m->meter_number,
                    'meter_name' => $m->meter_name,
                ]),
            'previous_reading' => ($prev = $meterReading->previousReading())
                ? MeterReadingResource::make($prev)->resolve()
                : null,
            'can' => [
                'update' => Auth::user()->can('update', $meterReading),
            ],
        ]);
    }

    /**
     * Update the specified meter reading
     *
     * ALL BUSINESS LOGIC DELEGATED TO SERVICE
     */
    public function update(UpdateMeterReadingRequest $request, MeterReading $meterReading): RedirectResponse
    {
        try {
            // Delegate to service
            $this->meterReadingService->updateReading($meterReading, $request->validated());

            return redirect()->route('meter-readings.show', $meterReading)
                ->with('success', 'Reading updated successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified meter reading
     *
     * ALL BUSINESS LOGIC DELEGATED TO SERVICE
     */
    public function destroy(MeterReading $meterReading): RedirectResponse
    {
        $this->authorize('delete', $meterReading);

        try {
            // Delegate to service - it handles billing check
            $this->meterReadingService->deleteReading($meterReading);

            return redirect()->route('meter-readings.index')
                ->with('success', 'Reading deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Distribute bulk meter reading to sub-meters
     */
    public function distribute(MeterReading $meterReading): RedirectResponse
    {
        $this->authorize('distribute', $meterReading);

        if (!$meterReading->meter->isBulkMeter()) {
            return back()->with('error', 'Reading is not from a bulk meter');
        }

        if ($meterReading->is_distributed) {
            return back()->with('error', 'Reading has already been distributed');
        }

        if (!$meterReading->meter->hasSubMeters()) {
            return back()->with('error', 'Bulk meter has no sub-meters');
        }

        try {
            // Delegate to BulkMeterService
            $distributedReadings = $this->bulkMeterService->distributeBulkReading($meterReading);

            return redirect()->route('meter-readings.show', $meterReading)
                ->with('success', "Reading distributed to " . count($distributedReadings) . " sub-meter(s)");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to distribute reading: ' . $e->getMessage());
        }
    }

    /**
     * Show readings for a specific meter
     */
    public function forMeter(Meter $meter): Response
    {
        $this->authorize('viewAny', MeterReading::class);

        $readings = $meter->readings()
            ->with('reader')
            ->latest('reading_date')
            ->paginate(20);

        // Transform using Resource
        $readings->through(fn($reading) => MeterReadingResource::make($reading)->resolve());

        return Inertia::render('meter-readings/for-meter', [
            'meter' => $meter->load('account'),
            'readings' => $readings,
            'average_consumption' => $meter->getAverageMonthlyConsumption(),
            'latest_reading' => $meter->getLatestReadingValue(),
            'can' => [
                'create' => Auth::user()->can('create', MeterReading::class),
            ],
        ]);
    }

    /**
     * Export meter readings to CSV
     *
     * Uses service for data retrieval, ExportService for CSV generation
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', MeterReading::class);

        // Get filters from request
        $filters = $request->only(['meter_id', 'reading_type', 'from_date', 'to_date']);

        // Delegate to service
        $readings = $this->meterReadingService->getReadingsForExport($filters);

        // Generate CSV using export service
        $exportService = app(\App\Services\Export\MeterReadingExportService::class);
        $csv = $exportService->generateCsv($readings);

        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            'meter_readings_' . now()->format('Y-m-d_His') . '.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * Show form for bulk reading entry
     */
    public function bulkCreate(): Response
    {
        $this->authorize('create', MeterReading::class);

        return Inertia::render('meter-readings/bulk-create', [
            'meters' => Meter::active()
                ->with('account')
                ->select('id', 'meter_number', 'meter_name', 'account_id')
                ->orderBy('meter_number')
                ->get()
                ->map(fn($m) => [
                    'id' => $m->id,
                    'meter_number' => $m->meter_number,
                    'meter_name' => $m->meter_name,
                    'account_name' => $m->account->name,
                    'current_reading' => $m->getCurrentReading(),
                    'last_reading_date' => $m->getLastReadingDate()?->format('Y-m-d'),
                ]),
        ]);
    }

    /**
     * Store bulk meter readings
     *
     * ALL BUSINESS LOGIC DELEGATED TO SERVICE
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $this->authorize('create', MeterReading::class);

        $validated = $request->validate([
            'readings' => 'required|array|min:1',
            'readings.*.meter_id' => 'required|exists:meters,id',
            'readings.*.reading' => 'required|numeric|min:0',
            'reading_date' => 'required|date|before_or_equal:today',
        ]);

        try {
            $readingDate = Carbon::parse($validated['reading_date']);
            $readingsWithDate = collect($validated['readings'])
                ->map(fn(array $r) => array_merge($r, ['reading_date' => $readingDate]))
                ->all();

            $result = $this->meterReadingService->createBulkReadings(
                $readingsWithDate,
                ['reading_date' => $readingDate->toDateString()]
            );

            if ($result['failed'] > 0) {
                return back()
                    ->with('warning', "Created {$result['created']} readings. {$result['failed']} failed.")
                    ->with('errors', $result['errors']);
            }

            return redirect()->route('meter-readings.index')
                ->with('success', "Successfully created {$result['created']} readings");
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
