<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Models\Meter;
use App\Models\Account;
use App\Services\Billing\BulkMeterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

/**
 * Meter Controller
 *
 * Handles meter management including bulk meters.
 * Follows thin controller pattern with business logic in services.
 *
 * @package App\Http\Controllers
 */
class MeterController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected BulkMeterService $bulkMeterService
    ) {
    }

    /**
     * Display a listing of meters
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Meter::class);

        $meters = Meter::query()
            ->with(['account', 'parentMeter'])
            ->when(
                $request->input('search'),
                fn ($q, $search) =>
                $q->where('meter_number', 'like', "%{$search}%")
                    ->orWhere('meter_name', 'like', "%{$search}%")
                    ->orWhereHas(
                        'account',
                        fn ($a) =>
                        $a->where('name', 'like', "%{$search}%")
                            ->orWhere('account_number', 'like', "%{$search}%")
                    )
            )
            ->when(
                $request->input('status'),
                fn ($q, $v) => $q->where('status', $v)
            )
            ->when(
                $request->input('type'),
                fn ($q, $v) => $q->where('type', $v)
            )
            ->when(
                $request->input('meter_type'),
                fn ($q, $v) => $q->where('meter_type', $v)
            )
            ->when(
                $request->input('account_id'),
                fn ($q, $v) => $q->where('account_id', $v)
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('meters/meters', [
            'meters' => $meters,
            'filters' => $request->only('search', 'status', 'type', 'meter_type', 'account_id'),
            'accounts' => Account::select('id', 'name', 'account_number')->get(),
            'can' => [
                'create' => $request->user()->can('create', Meter::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new meter
     */
    public function create(): Response
    {
        $this->authorize('create', Meter::class);

        return Inertia::render('meters/create', [
            'accounts' => Account::active()
                ->select('id', 'name', 'account_number')
                ->get(),
            'bulkMeters' => Meter::where('meter_type', 'bulk')
                ->where('status', 'active')
                ->select('id', 'meter_number', 'meter_name')
                ->get(),
            'can' => [
                'create' => Auth::user()->can('create', Meter::class),
            ],
        ]);
    }

    /**
     * Store a newly created meter
     */
    public function store(StoreMeterRequest $request): RedirectResponse
    {
        $meter = Meter::create($request->validated());

        // If this is a sub-meter, validate parent allocation
        if ($meter->parent_meter_id) {
            $validation = $this->bulkMeterService->validateBulkMeterSetup($meter->parentMeter);

            if (!$validation['valid']) {
                return back()->with(
                    'warning',
                    'Meter created but allocation warning: ' . implode(', ', $validation['errors'])
                );
            }
        }

        return redirect()->route('meters.show', $meter)
            ->with('success', 'Meter created successfully');
    }

    /**
     * Display the specified meter
     */
    public function show(Meter $meter): Response
    {
        $this->authorize('view', $meter);

        $meter->load([
            'account',
            'parentMeter',
            'subMeters.account',
            'readings' => function ($query) {
                $query->latest('reading_date')->limit(10);
            },
            'billingDetails' => function ($query) {
                $query->latest()->limit(5);
            },
        ]);

        // Get readings separately
        $readings = $meter->readings()
            ->latest('reading_date')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'reading_date' => $r->reading_date,
                'reading_value' => $r->reading_value,
                'consumption' => $r->consumption,
                'reading_type' => $r->reading_type,
                'notes' => $r->notes,
            ]);

        // Get billing details separately (FIXED)
        $billingDetails = \App\Models\BillingDetail::where('meter_id', $meter->id)
            ->with('billing')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($detail) => [
                'id' => $detail->id,
                'billing_id' => $detail->billing_id,
                'meter_id' => $detail->meter_id,
                'previous_reading' => $detail->previous_reading_value,
                'current_reading' => $detail->current_reading_value,
                'units' => $detail->units_used,
                'amount' => $detail->amount,
                'billing' => [
                    'id' => $detail->billing->id,
                    'billing_period' => $detail->billing->billing_period,
                    'status' => $detail->billing->status,
                    'total_amount' => $detail->billing->total_amount,
                ],
            ]);

        // Get bills separately (FIXED)
        $bills = $meter->account
            ? $meter->account->billings()
            ->where('status', '!=', 'voided')
            ->latest('billing_period')
            ->limit(10)
            ->get()
            ->map(fn ($bill) => [
                'id' => $bill->id,
                'billing_period' => $bill->billing_period,
                'total_amount' => $bill->total_amount,
                'status' => $bill->status,
                'due_date' => $bill->due_date,
                'issued_at' => $bill->issued_at,
            ])
            : collect([]);

        $data = [
            'meter' => [
                'id' => $meter->id,
                'meter_number' => $meter->meter_number,
                'meter_name' => $meter->meter_name,
                'meter_type' => $meter->meter_type,
                'type' => $meter->type,
                'status' => $meter->status,
                'location' => $meter->location,
                'installed_at' => $meter->installed_at,
                'account_id' => $meter->account_id,
                'parent_meter_id' => $meter->parent_meter_id,
                'allocation_percentage' => $meter->allocation_percentage,
                'account' => $meter->account,
                'parent_meter' => $meter->parentMeter,
                'sub_meters' => $meter->subMeters,
                'summary' => $meter->getSummary(),
            ],
            'readings' => $readings,
            'billing_details' => $billingDetails,
            'bills' => $bills,
            'can' => [
                'update' => Auth::user()->can('update', $meter),
                'delete' => Auth::user()->can('delete', $meter),
                'createReading' => Auth::user()->can('createReading', $meter),
                'manageSubMeters' => Auth::user()->can('manageSubMeters', $meter),
            ],
        ];

        // Add bulk meter validation if applicable
        if ($meter->isBulkMeter()) {
            $data['bulk_validation'] = $this->bulkMeterService->validateBulkMeterSetup($meter);
        }

        return Inertia::render('meters/show', $data);
    }

    /**
     * Show the form for editing the specified meter
     */
    public function edit(Meter $meter): Response
    {
        $this->authorize('update', $meter);

        return Inertia::render('meters/edit', [
            'meter' => $meter,
            'accounts' => Account::active()
                ->select('id', 'name', 'account_number')
                ->get(),
            'bulkMeters' => Meter::where('meter_type', 'bulk')
                ->where('status', 'active')
                ->where('id', '!=', $meter->id) // Exclude current meter
                ->select('id', 'meter_number', 'meter_name')
                ->get(),
            'can' => [
                'update' => Auth::user()->can('update', $meter),
            ],
        ]);
    }

    /**
     * Update the specified meter
     */
    public function update(UpdateMeterRequest $request, Meter $meter): RedirectResponse
    {
        $meter->update($request->validated());

        // If allocation percentage changed, validate parent
        if ($meter->parent_meter_id && $request->has('allocation_percentage')) {
            $validation = $this->bulkMeterService->validateBulkMeterSetup($meter->parentMeter);

            if (!$validation['valid']) {
                return back()->with(
                    'warning',
                    'Meter updated but allocation warning: ' . implode(', ', $validation['errors'])
                );
            }
        }

        return redirect()->route('meters.show', $meter)
            ->with('success', 'Meter updated successfully');
    }

    /**
     * Remove the specified meter
     */
    public function destroy(Meter $meter): RedirectResponse
    {
        $this->authorize('delete', $meter);

        // Check if meter has readings
        if ($meter->readings()->exists()) {
            return back()->with('error', 'Cannot delete meter with existing readings');
        }

        // Check if meter has billing details
        if ($meter->billingDetails()->exists()) {
            return back()->with('error', 'Cannot delete meter with billing history');
        }

        // Check if meter has sub-meters
        if ($meter->isBulkMeter() && $meter->hasSubMeters()) {
            return back()->with('error', 'Cannot delete bulk meter with active sub-meters');
        }

        $meter->delete();

        return redirect()->route('meters.index')
            ->with('success', 'Meter deleted successfully');
    }

    /**
     * Adjust sub-meter allocations for a bulk meter
     */
    public function adjustAllocations(Request $request, Meter $meter): RedirectResponse
    {
        $this->authorize('manageSubMeters', $meter);

        if (!$meter->isBulkMeter()) {
            return back()->with('error', 'Meter is not a bulk meter');
        }

        $request->validate([
            'allocations' => 'required|array',
            'allocations.*' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $this->bulkMeterService->adjustSubMeterAllocations(
                $meter,
                $request->input('allocations')
            );

            return back()->with('success', 'Sub-meter allocations updated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update allocations: ' . $e->getMessage());
        }
    }

    /**
     * Validate bulk meter setup
     */
    public function validateBulkSetup(Meter $meter): RedirectResponse
    {
        $this->authorize('view', $meter);

        if (!$meter->isBulkMeter()) {
            return back()->with('error', 'Meter is not a bulk meter');
        }

        $validation = $this->bulkMeterService->validateBulkMeterSetup($meter);

        if ($validation['valid']) {
            return back()->with('success', 'Bulk meter setup is valid');
        } else {
            return back()->with('error', 'Validation errors: ' . implode(', ', $validation['errors']));
        }
    }

    /**
     * Export meters to CSV
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Meter::class);

        $query = Meter::with('account');

        // Apply same filters as index
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $meters = $query->get();

        $csv = $this->generateMetersCsv($meters);

        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            'meters_' . now()->format('Y-m-d_His') . '.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * Generate CSV from meters collection
     */
    protected function generateMetersCsv($meters): string
    {
        $output = fopen('php://temp', 'r+');

        // Headers
        fputcsv($output, [
            'Meter Number',
            'Meter Name',
            'Type',
            'Meter Type',
            'Status',
            'Account Number',
            'Account Name',
            'Parent Meter',
            'Allocation %',
            'Latest Reading',
            'Installed Date',
        ]);

        // Data
        foreach ($meters as $meter) {
            fputcsv($output, [
                $meter->meter_number,
                $meter->meter_name,
                $meter->type,
                $meter->meter_type,
                $meter->status,
                optional($meter->account)->account_number,
                optional($meter->account)->name,
                $meter->parentMeter?->meter_number,
                $meter->allocation_percentage,
                $meter->getLatestReadingValue(),
                $meter->installed_at?->format('Y-m-d'),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
