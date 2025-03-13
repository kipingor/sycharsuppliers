<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        return Customer::with(['meters', 'bills', 'payments'])->paginate(10);
    }

    public function show($id)
    {
        return Customer::with(['meters', 'bills', 'payments'])->findOrFail($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'required|string|unique:customers,phone',
            'address' => 'nullable|string',
        ]);

        $customer = Customer::create($request->all());

        return response()->json(['message' => 'Customer created successfully', 'customer' => $customer]);
    }

    public function activate($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->activate();

        return response()->json(['message' => 'Customer activated successfully']);
    }

    public function deactivate($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->deactivate();

        return response()->json(['message' => 'Customer deactivated successfully']);
    }
}
