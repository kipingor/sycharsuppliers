<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillingRequest;
use App\Http\Requests\UpdateBillingRequest;
use App\Models\Billing;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\BillingMeterReadingDetail;
use Illuminate\Support\Facades\Mail;
use App\Mail\BillingNotification;
use App\Models\EmailLog;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('billing/billing', [
            'bills' => Billing::with('meter.customer')->latest()->paginate(10),
            'meters' => Meter::with('customer')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('billing/Create', [
            'meters' => Meter::select('id', 'name')->with('customers')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBillingRequest $request)
    {
        // Get the previous meter reading
        $previousReading = MeterReading::where('meter_id', $request->meter_id)
            ->latest()
            ->first();

        $previousValue = $previousReading ? $previousReading->reading_value : 0;
        $unitsUsed = $request->reading_value - $previousValue;

        


        // Get unit price from config
        $unitPrice = config('price.unit_price');
        $amountDue = $unitsUsed * $unitPrice;

         // Store new meter reading
         MeterReading::create([
            'meter_id' => $request->meter_id,
            'reading_date' => now(),
            'reading_value' => $request->reading_value,
        ]);

        // Create the bill
        $bill = Billing::create([
            'meter_id' => $request->meter_id,            
            'amount_due' => $amountDue,
            'status' => 'pending',
        ]);

        BillingMeterReadingDetail::create([
            'billing_id' => $bill->id,
            'previous_reading_value' => $previousValue,
            'current_reading_value' => $request->reading_value,
            'units_used' => $unitsUsed,
        ]);

        return to_route('billing.index')
        ->with('status', 'Bill created successfully!')
        ->with('summary', [
            // 'meter_number' => $meter->meter_number,
            // 'customer' => $meter->customer->name,
            'units_used' => $unitsUsed,
            'amount_due' => $amountDue,
            'unit_price' => $unitPrice,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Billing $billing)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Billing $billing)
    {
        return Inertia::render('billing/Edit', [
            'bill' => $bill->load('customer'),
            'meters' => Meter::select('id', 'name')->with('customers')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBillingRequest $request, Billing $billing)
    {
        $bill->update($request->validated());

        return to_route('billing.index')->with('status', 'Bill updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Billing $billing)
    {
        $billing->delete();

        return to_route('billing.index')->with('status', 'Bill deleted successfully!');
    }

    public function sendBillEmail(Meter $meter, Billing $billing)
    {
        $email = $meter->customer->company ? $meter->customer->email : $meter->customer->contact_email;
        $subject = 'Water Bill Notification';
        $content = 'Your bill amount is: ' . $billing->amount_due;

        Mail::to($email)->send(new BillNotification($subject, $content));

        // Log the email
        EmailLog::create([
            'recipient_email' => $email,
            'subject' => $subject,
            'content' => $content,
            'status' => 'sent',
        ]);
    }

    public function markPaid($id)
    {
        $bill = Billing::findOrFail($id);
        $bill->markAsPaid();

        return response()->json(['message' => 'Bill marked as paid']);
    }

    public function applyLateFees()
    {
        $overdueBills = Billing::where('status', 'pending')->get();
        foreach ($overdueBills as $bill) {
            $bill->applyLateFee();
        }

        return response()->json(['message' => 'Late fees applied']);
    }
}
