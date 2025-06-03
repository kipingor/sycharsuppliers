<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Payment;
use App\Models\Meter;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentRequest $request)
    {
        $payment = Payment::create([
            'meter_id' => $request->meter_id,
            'amount' => $request->amount,
            'method' => $request->method,
            'transaction_id' => $request->transaction_id,
            'payment_date' => $request->payment_date,
            'status' => $request->status,
        ]);

        // Update the related billing status if needed
        $meter = Meter::findOrFail($request->meter_id);
        $billing = $meter->bills()->where('status', 'pending')->first();
        
        if ($billing) {
            $billing->update(['status' => 'paid']);
        }

        return redirect()->back()->with('status', 'Payment recorded successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {
        //
    }
}
