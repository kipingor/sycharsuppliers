<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        return Payment::with('customer', 'bill')->paginate(10);
    }

    public function show($id)
    {
        return Payment::with('customer', 'bill')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'bill_id' => 'nullable|exists:billings,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string',
            'transaction_id' => 'nullable|string|unique:payments,transaction_id',
            'payment_date' => 'nullable|date',
        ]);

        $payment = Payment::create($request->all());

        return response()->json(['message' => 'Payment recorded successfully', 'payment' => $payment]);
    }

    public function markAsCompleted($id)
    {
        $payment = Payment::findOrFail($id);
        $payment->update(['status' => 'completed']);

        return response()->json(['message' => 'Payment marked as completed']);
    }
}
