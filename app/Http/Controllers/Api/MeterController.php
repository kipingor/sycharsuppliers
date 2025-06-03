<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Meter;
use App\Models\Billing;
use App\Models\MeterReading;
use App\Models\BillingDetail;
use App\Models\Payment;
use App\Services\EmailService;
use App\Mail\BillingStatement;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;
// use Spatie\LaravelPdf\Support\pdf;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class MeterController extends Controller
{
    public function index()
    {
        return Meter::with('resident')->paginate(10);
    }    

    public function billPayments(Meter $meter): RedirectResponse
    {
        $meter->load(['bills.payments', 'bills.details']);

        return Redirect::back()->with($meter);
    }

    public function sendStatement(Meter $meter): RedirectResponse
    {        
        $resident = $meter->resident;
        if (!$resident || !$resident->email) {
            return back()->with(['error' => 'Resident email not found.'], 422);
        }

        $startDate = now()->startOfYear()->toDateString();
        $endDate = now()->toDateString();

        //calculate carry forward Balabce
        $carryForward = ($meter->bills()->where('created_at', '<', $startDate)->sum('amount_due')) - ($meter->payments()->where('created_at', '<', $startDate)->sum('amount'));

        //Get transactions from start of the year
        $transactions = $meter->bills()
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->get()
                            ->merge($meter->payments()
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->orderBy('created_at', 'desc')
                            ->get());

        
        $pdf = Pdf::loadView('pdf.statement', compact('transactions', 'carryForward', 'startDate', 'endDate', 'resident'));


        $billing = $meter->bills()->latest()->first();
        if (!$billing) {
            return back()->with(['error' => 'No billing found for this meter.'], 404);
        }

        $details = $billing->details() ->first();    

        $billing = Billing::where('meter_id', $meter->id)->latest()->first(); // Not just a query
        $details = BillingDetail::where('billing_id', $billing->id)->latest()->first(); // same here

        $total_billed = Billing::where('meter_id', $meter->id)->sum('amount_due');
        $meter = Meter::where('id', $meter->id)->withSum('payments', 'amount')->first();
        $total_paid = $meter->payments_sum_amount ?? 0;
        $balance_due = $total_billed - $total_paid;
    
        // Send the email
        $email = new BillingStatement($resident, $meter, $billing, $details, $total_billed, $total_paid, $balance_due);
        $email->attachData($pdf->output(), 'statement-'. $resident->name . '-'. $endDate . '.pdf', ['mime' => 'application/pdf']);
        
        Mail::to($resident->email)->send($email);

        return Redirect::back()->with('status', 'Statement sent successfully!');
    }

    public function show($id)
    {
        return Meter::with('resident', 'meterReadings')->findOrFail($id);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'resident_id' => 'required|exists:residents,id',
            'meter_number' => 'required|string|unique:meters,meter_number',
            'location' => 'nullable|string',
            'installation_date' => 'nullable|date',
        ]);

        $meter = Meter::create($request->all());

        return Redirect::back()->with(['message' => 'Meter added successfully', 'meter' => $meter]);
    }

    public function activate($id): RedirectResponse
    {
        $meter = Meter::findOrFail($id);
        $meter->activate();

        return Redirect::back()->with(['message' => 'Meter activated successfully']);
    }

    public function deactivate($id): RedirectResponse
    {
        $meter = Meter::findOrFail($id);
        $meter->deactivate();

        return Redirect::back()->with(['message' => 'Meter deactivated successfully']);
    }

    public function readingList()
    {
        $meters = Meter::with('resident', 'meterReadings')->where('status', 'active')->get();
        
        $pdf = Pdf::loadView('pdf.meter_reading_list', compact('meters'));
        return $pdf->download('meter_reading_list.pdf');
    }
}
