<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResidentRequest;
use App\Http\Requests\UpdateResidentRequest;
use App\Models\Resident;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class ResidentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('residents/residents', [
            'residents' => Resident::latest()->paginate(10),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('residents/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreResidentRequest $request)
    {
        $resident = Resident::create(array_merge($request->validated(), ['account_number' => Str::random(6)]));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     */
    public function show(Resident $resident)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Resident $resident)
    {
        return Inertia::render('residents/edit', [
            'resident' => $resident,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateResidentRequest $request, Resident $resident)
    {
        $resident->update($request->validated());

        return to_route('residents.index', $resident)->with('status', 'Profile updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Resident $resident)
    {
        $resident->delete();

        return to_route('residents.index')->with('status', 'Resident deleted successfully.');
    }

    public function generateStatementToken()
    {
        $this->statement_token = Str::random(60); // Generate a random token
        $this->token_expiry = now()->addHours(24); // Token expires in 24 hours
        $this->save();
    }
}
