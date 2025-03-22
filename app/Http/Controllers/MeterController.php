<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Models\Meter;
use App\Models\Customer;
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

        $meters = Meter::with('customer')
            ->with(['meterReadings' => function ($query) {
                $query->selectRaw('meter_id, max(reading_value) - min(reading_value) as total_units')
                    ->groupBy('meter_id');
            }])
            ->withSum('bills', 'amount_due')
            ->withSum(['bills as total_paid' => function ($query) {
                $query->join('payments', 'billings.id', '=', 'payments.billing_id')
                    ->where('payments.status', 'completed');
            }], 'payments.amount')
            ->when($search, function ($query, $search) {
                $query->where('meter_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
            ->paginate(10)
            ->through(function ($meter) {
                $totalBilled = $meter->bills_sum_amount_due ?? 0;
                $totalPaid = $meter->total_paid ?? 0;
                $balanceDue = $totalBilled - $totalPaid;

                return [
                    'id' => $meter->id,
                    'meter_number' => $meter->meter_number,
                    'customer' => $meter->customer,
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
        ]);
        
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('meters/Create', [
            'customers' => Customer::select('id', 'name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMeterRequest $request)
    {
        Meter::create($request->validated());

        return to_route('meters.index')->with('status', 'Meter added successfully!');
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

        return to_route('meters.index')->with('status', 'Meter updated successfully!');
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
