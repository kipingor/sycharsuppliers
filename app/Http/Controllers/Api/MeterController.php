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


class MeterController extends Controller
{
    public function index()
    {
        return Meter::with('resident')->paginate(10);
    }    

    public function billPayments(Meter $meter): RedirectResponse
    {
        $meter->load(['bills.payments', 'bills.details']);

        return Redirect::back()->with('meter', $meter);
    }

    public function sendStatement(Meter $meter): RedirectResponse
    {        
        $resident = $meter->resident;
        if (!$resident || !$resident->email) {
            return back()->with('error', 'Resident email not found.');
        }

        $startDate = now()->startOfYear()->toDateString();
        $endDate = now()->toDateString();

        //calculate carry forward Balabce
        $carryForward = ($meter->bills()->where('created_at', '<', $startDate)->where('status', '!=', 'void')->sum('amount_due')) - ($meter->payments()->where('created_at', '<', $startDate)->sum('amount'));

        //Get transactions from start of the year (eager-load billing details for bills)
        $transactions = $meter->bills()
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->where('status', '!=', 'void')
                            ->with('details')
                            ->get()
                            ->merge($meter->payments()
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->get());

        // Determine current and previous meter readings for the statement
        $latestReading = $meter->meterReadings()->orderBy('reading_date', 'desc')->first();
        $currentMeterReading = $latestReading?->reading_value;
        $previousMeterReading = $meter->meterReadings()->orderBy('reading_date', 'desc')->skip(1)->first()?->reading_value;

        $pdf = Pdf::loadView('pdf.statement', [
            'transactions' => $transactions,
            'carryForward' => $carryForward,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'resident' => $resident,
            'meter' => $meter,
            'current_meter_reading' => $currentMeterReading,
            'previous_meter_reading' => $previousMeterReading,
        ]);


        $billing = $meter->bills()->latest()->first();
        if (!$billing) {
            return back()->with('error', 'No billing found for this meter.');
        }

        $details = $billing->details() ->first();    

        $total_billed = Billing::where('meter_id', $meter->id)->sum('amount_due');
        $total_paid = Payment::where('meter_id', $meter->id)->sum('amount');
        $balance_due = $total_billed - $total_paid;
    
        // Send the email
        $email = new BillingStatement($resident, $meter, $billing, $details, $total_billed, $total_paid, $balance_due);
        $email->attachData($pdf->output(), 'statement-'. $resident->name . '-'. $endDate . '.pdf', ['mime' => 'application/pdf']);
        
        Mail::to($resident->email)->send($email);

        return Redirect::back()->with('status', 'Statement sent successfully!');
    }

    public function downloadAllStatements()
    {
        // Increase execution time and reduce memory pressure for large batches
        @set_time_limit(300);

        // Initialize FPDI to combine all individual PDFs into one.
        $combinedPdf = new Fpdi();

        $processed = 0;

        // Process active meters in chunks to avoid loading everything into memory
        Meter::where('status', 'active')
            ->with(['resident', 'meterReadings' => function ($query) {
                $query->orderBy('reading_date', 'desc');
            }])
            ->chunkById(25, function ($meters) use (&$combinedPdf, &$processed) {
                foreach ($meters as $meter) {
                    // Skip if the meter does not have an associated resident or the resident's email is missing.
                    if (!$meter->resident || !$meter->resident->email) {
                        continue;
                    }

                    $startDate = now()->startOfYear()->toDateString();
                    $endDate = now()->toDateString();

                    // Calculate the carry forward balance from before the statement period.
                    $carryForward = ($meter->bills()->where('created_at', '<', $startDate)->where('status', '!=', 'void')->sum('amount_due')) -
                                    ($meter->payments()->where('created_at', '<', $startDate)->sum('amount'));

                    // Retrieve bills for the current period, excluding voided ones, and eager-load details.
                    $bills = $meter->bills()
                                    ->whereBetween('created_at', [$startDate, $endDate])
                                    ->where('status', '!=', 'void')
                                    ->with('details')
                                    ->get();

                    // Retrieve payments for the current period.
                    $payments = $meter->payments()
                                        ->whereBetween('created_at', [$startDate, $endDate])
                                        ->get();

                    // Merge bills and payments, then sort all transactions by their creation date.
                    $transactions = $bills->merge($payments)->sortBy('created_at');

                    // Initialize meter reading variables.
                    $currentMeterReading = null;
                    $previousMeterReading = null;

                    // If meter readings exist, get the latest and the one before it from the eager-loaded collection.
                    if ($meter->meterReadings->isNotEmpty()) {
                        $latestMeterReadingRecord = $meter->meterReadings->first();
                        $currentMeterReading = $latestMeterReadingRecord->reading_value;

                        if ($meter->meterReadings->count() > 1) {
                            $previousMeterReadingRecord = $meter->meterReadings->skip(1)->first();
                            $previousMeterReading = $previousMeterReadingRecord->reading_value;
                        }
                    }

                    // Generate the PDF for the current meter's statement.
                    $pdf = Pdf::loadView('pdf.statement', [
                        'transactions' => $transactions,
                        'carryForward' => $carryForward,
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'resident' => $meter->resident,
                        'meter' => $meter,
                        'current_meter_reading' => $currentMeterReading,
                        'previous_meter_reading' => $previousMeterReading,
                    ]);

                    // Save the raw PDF to a temporary file for FPDI to process.
                    $tmpPath = storage_path('app/tmp_statement_' . $meter->id . '.pdf');
                    file_put_contents($tmpPath, $pdf->output());

                    // Import pages from the temporary PDF into the combined PDF.
                    $pageCount = $combinedPdf->setSourceFile($tmpPath);
                    for ($i = 1; $i <= $pageCount; $i++) {
                        $tpl = $combinedPdf->importPage($i);
                        $size = $combinedPdf->getTemplateSize($tpl);
                        $combinedPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $combinedPdf->useTemplate($tpl);
                    }

                    // Delete the temporary file after it has been processed.
                    @unlink($tmpPath);

                    $processed++;
                }
            });

        if ($processed === 0) {
            return back()->with('error', 'No statements available to download.');
        }

        // Define the path for the final combined PDF.
        $finalPdfPath = storage_path('app/all-meter-statements.pdf');
        // Output the combined PDF to the specified file path.
        $combinedPdf->Output($finalPdfPath, 'F');

        // Return the combined PDF as a download response.
        // The file will be deleted from storage after being sent to the user.
        return response()->download($finalPdfPath, 'all-meter-statements-' . date('Y-m-d') . '.pdf')->deleteFileAfterSend(true);
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
