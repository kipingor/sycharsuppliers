<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResidentRequest;
use App\Http\Requests\UpdateResidentRequest;
use App\Models\Resident;
use Illuminate\Http\Request;
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
    public function index(Request $request)
    {
        // Authorize user to view residents
        $this->authorize('viewAny', Resident::class);

        $search = $request->input('search');

        return Inertia::render('residents/residents', [
            'residents' => Resident::query()
                ->when($search, function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only('search'),
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
