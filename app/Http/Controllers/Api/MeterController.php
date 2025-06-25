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
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi; // Optional if merging PDFs
use setasign\Fpdf\Fpdf;

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
        $carryForward = ($meter->bills()->where('created_at', '<', $startDate)->where('status', '<>', 'void')->sum('amount_due')) - ($meter->payments()->where('created_at', '<', $startDate)->sum('amount'));

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

    public function downloadAllStatements()
    {
        $meters = Meter::with('resident')->where('status', 'active')->get();
        $pdfs = [];

        foreach ($meters as $meter) {
            if (!$meter->resident || !$meter->resident->email) {
                continue; // Skip if no resident/email
            }

            $startDate = now()->startOfYear()->toDateString();
            $endDate = now()->toDateString();

            $carryForward = ($meter->bills()->where('created_at', '<', $startDate)->where('status', '<>', 'void')->sum('amount_due')) -
                            ($meter->payments()->where('created_at', '<', $startDate)->sum('amount'));

            $transactions = $meter->bills()
                                ->whereBetween('created_at', [$startDate, $endDate])
                                ->where('status', '<>', 'void')
                                ->get()
                                ->merge(
                                    $meter->payments()
                                        ->whereBetween('created_at', [$startDate, $endDate])
                                        ->orderBy('created_at', 'desc')
                                        ->get()
                                )
                                ->sortBy('created_at'); // Optional: ensure proper order

            $pdf = Pdf::loadView('pdf.statement', [
                'transactions' => $transactions,
                'carryForward' => $carryForward,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'resident' => $meter->resident,
                'meter' => $meter,
            ]);

            $pdfs[$meter->id] = $pdf->output(); // Store using meter ID
        }

        if (empty($pdfs)) {
            return back()->with('error', 'No statements available to download.');
        }

        // Combine all PDFs using FPDI
        $combinedPdf = new Fpdi();

        foreach ($pdfs as $meterId => $rawPdf) {
            $tmpPath = storage_path('app/tmp_statement_' . $meterId . '.pdf'); // FIXED here
            file_put_contents($tmpPath, $rawPdf);

            $pageCount = $combinedPdf->setSourceFile($tmpPath);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tpl = $combinedPdf->importPage($i);
                $size = $combinedPdf->getTemplateSize($tpl);
                $combinedPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $combinedPdf->useTemplate($tpl);
            }

            unlink($tmpPath);
        }

        $finalPdfPath = storage_path('app/all-meter-statements.pdf');
        $combinedPdf->Output($finalPdfPath, 'F');

        return response()->download($finalPdfPath, 'all-meter-statements-' . date('y-m-d') . '.pdf')->deleteFileAfterSend(true);
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
