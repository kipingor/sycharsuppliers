<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Models\Meter;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $residents = Resident::select('id', 'name')->get();

        $meters = Meter::with('resident', 'bills', 'payments')
            ->with(['meterReadings' => function ($query) {
                $query->selectRaw('meter_id, max(reading_value) - min(reading_value) as total_units')
                    ->groupBy('meter_id');
            }])
            ->withSum(['bills' => function ($query) {
                $query->where('status', '!=', 'void');
            }], 'amount_due')
            ->withSum('payments', 'amount')            
            ->when($search, function ($query, $search) {
                $query->where('meter_number', 'like', "%{$search}%")
                    ->orWhereHas('resident', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
            ->paginate(10)
            ->through(function ($meter) {
                $totalBilled = $meter->bills_sum_amount_due ?? 0;
                $totalPaid = $meter->payments_sum_amount ?? 0;
                $balanceDue = $totalBilled - $totalPaid;

                return [
                    'id' => $meter->id,
                    'meter_number' => $meter->meter_number,
                    'resident' => $meter->resident,
                    'location' => $meter->location,
                    'total_units' => $meter->meterReadings->first()->total_units ?? 0,
                    'total_billed' => $totalBilled,
                    'total_paid' => $totalPaid,
                    'balance_due' => $balanceDue,
                    'status' => $meter->status,
                ];
            });

        return Inertia::render('meters/meters', [
            'meters' => $meters,
            'residents' => $residents,
        ]);
        
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('meters/Create', [
            'residents' => Resident::select('id', 'name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMeterRequest $request)
    {
        Meter::create($request->validated());

        return redirect()->back()->with('success', 'Meter added successfully!');

        // return to_route('meters.index')->with('status', 'Meter added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Meter $meter)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Meter $meter)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMeterRequest $request, Meter $meter)
    {
        $meter->update($request->validated());

        return redirect()->back()->with('success', 'Meter updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Meter $meter)
    {
        $meter->delete();

        return to_route('meters.index')->with('status', 'Meter deleted successfully!');
    }

    public function latestReading(Meter $meter)
    {
        $latestReading = $meter->meterReadings()->latest('reading_date')->first();
        return response()->json($latestReading);
    }
}
