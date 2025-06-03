<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillingRequest;
use App\Http\Requests\UpdateBillingRequest;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use App\Models\Billing;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\BillingDetail;
use Illuminate\Support\Facades\Mail;
use App\Mail\BillingNotification;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Billing::with('meter.resident');

        if ($search = $request->input('search')) {
            $query->whereHas('meter.resident', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        $bills = $query->latest()->paginate(10);
        
        return Inertia::render('billing/billing', [
            'bills' => $bills,
            'meters' => Meter::with('resident')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('billing/Create', [
            'meters' => Meter::select('id', 'name')->with('residents')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'meter_id' => 'required|exists:meters,id',
            'reading_value' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    $lastReading = MeterReading::where('meter_id', $request->meter_id)
                        ->latest('reading_date')
                        ->value('reading_value');
    
                    if ($lastReading !== null && $value <= $lastReading) {
                        $fail("The new reading must be greater than the last reading ({$lastReading}).");
                    }
                },
            ],
        ]);
        // Get the previous meter reading
        $previousReading = MeterReading::where('meter_id', $request->meter_id)
            ->latest('reading_date')
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
        $billing = Billing::create([
            'meter_id' => $request->meter_id,            
            'amount_due' => $amountDue,
            'status' => 'pending',
        ]);

        BillingDetail::create([
            'billing_id' => $billing->id,
            'previous_reading_value' => $previousValue,
            'current_reading_value' => $request->reading_value,
            'units_used' => $unitsUsed,
        ]);

        return redirect()->back();

        // return to_route('billing.index')
        // ->with('status', 'Bill created successfully!')
        // ->with('summary', [
        //     // 'meter_number' => $meter->meter_number,
        //     // 'resident' => $meter->resident->name,
        //     'units_used' => $unitsUsed,
        //     'amount_due' => $amountDue,
        //     'unit_price' => $unitPrice,
        // ]);
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
            'bill' => $billing->load('resident'),
            'meters' => Meter::select('id', 'name')->with('residents')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBillingRequest $request, Billing $billing)
    {
        $billing->update($request->validated());

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
        $email = $meter->resident->company ? $meter->resident->email : $meter->resident->contact_email;
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

    public function statement(Meter $meter)
    {
        $totalDue = $meter->bills->sum('amount');
        $totalPaid = $meter->bills->sum('paid_amount');
        $balance = $totalDue - $totalPaid;

        return Inertia::render('Billing/Statement', [
            'meter' => $meter->load('resident'),
            'bills' => $meter->bills,
            'totalDue' => $totalDue,
            'totalPaid' => $totalPaid,
            'balance' => $balance,
        ]);
    }
}
