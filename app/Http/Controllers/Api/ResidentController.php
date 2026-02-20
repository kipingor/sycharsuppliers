<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Http\Requests\StoreResidentRequest;
use Illuminate\Http\Request;

class ResidentController extends Controller
{
    public function index()
    {
        return Resident::with(['meters', 'bills', 'payments'])->paginate(10);
    }

    public function show($id)
    {
        return Resident::with(['meters', 'bills', 'payments'])->findOrFail($id);
    }

    public function store(StoreResidentRequest $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:residents,email',
            'phone' => 'required|string|unique:residents,phone',
            'address' => 'nullable|string',
        ]);

        $resident = Resident::create($request->all());

        return response()->json(['message' => 'Resident created successfully', 'resident' => $resident]);
    }

    public function activate($id)
    {
        $resident = Resident::findOrFail($id);
        $resident->activate();

        return response()->json(['message' => 'Resident activated successfully']);
    }

    public function deactivate($id)
    {
        $resident = Resident::findOrFail($id);
        $resident->deactivate();

        return response()->json(['message' => 'Resident deactivated successfully']);
    }
}
