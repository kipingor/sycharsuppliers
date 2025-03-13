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
    public function index()
    {
        return Inertia::render('meters/Index', [
            'meters' => Meter::with('customer')->paginate(10),
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
}
