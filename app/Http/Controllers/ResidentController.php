<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResidentRequest;
use App\Http\Requests\UpdateResidentRequest;
use App\Models\Resident;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class ResidentController extends Controller
{
    use AuthorizesRequests;
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Authorize user to view residents
        $this->authorize('viewAny', Resident::class);
        
        return Inertia::render('residents/residents', [
            'residents' => Resident::latest()->paginate(10),
            'can' => [
                'create' => Auth::user()->can('create', Resident::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Resident::class);
        
        return Inertia::render('residents/create', [
            'can' => [
                'create' => Auth::user()->can('create', Resident::class),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreResidentRequest $request)
    {
        $resident = Resident::create(array_merge($request->validated(), ['account_number' => Str::random(6)]));

        return redirect()->route('residents.index')
            ->with('success', 'Resident created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Resident $resident)
    {
        $this->authorize('view', $resident);
        
        return Inertia::render('residents/show', [
            'resident' => $resident,
            'can' => [
                'update' => Auth::user()->can('update', $resident),
                'delete' => Auth::user()->can('delete', $resident),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Resident $resident)
    {
        $this->authorize('update', $resident);
        
        return Inertia::render('residents/edit', [
            'resident' => $resident,
            'status' => session('status'),
            'can' => [
                'update' => Auth::user()->can('update', $resident),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateResidentRequest $request, Resident $resident)
    {
        $resident->update($request->validated());

        return redirect()->route('residents.index')
            ->with('success', 'Resident updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Resident $resident)
    {
        $this->authorize('delete', $resident);
        
        $resident->delete();

        return redirect()->route('residents.index')
            ->with('success', 'Resident deleted successfully');
    }
}
