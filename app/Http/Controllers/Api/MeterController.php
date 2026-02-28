<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Mail\BillingStatement;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;

class MeterController extends Controller
{
    public function index()
    {
        return Meter::with(['resident', 'account'])->paginate(10);
    }

    public function billPayments(Meter $meter): RedirectResponse
    {
        $meter->load([
            'account.billings.details',
            'account.billings.allocations.payment'
        ]);

        return Redirect::back()->with('meter', $meter);
    }

    public function sendStatement(Meter $meter): RedirectResponse
    {
        // Ensure account and resident are loaded
        $meter->load(['resident', 'account']);

        $resident = $meter->resident;
        if (!$resident || !$resident->email) {
            return back()->with('error', 'Resident email not found.');
        }

        if (!$meter->account) {
            return back()->with('error', 'Meter not associated with an account.');
        }

        $account = $meter->account;
        $startDate = now()->startOfYear()->toDateString();
        $endDate = now()->toDateString();

        // Calculate carry forward balance
        $carryForwardBillings = $account->billings()
            ->where('created_at', '<', $startDate)
            ->whereNotIn('status', ['voided'])
            ->sum('total_amount');  // FIXED: was amount_due

        $carryForwardPayments = $account->payments()
            ->where('created_at', '<', $startDate)
            ->where('status', 'completed')  // Only count completed payments
            ->sum('amount');

        $carryForward = $carryForwardBillings - $carryForwardPayments;

        // Get transactions from start of the year
        $billings = $account->billings()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('status', ['voided'])
            ->with('details')
            ->get();

        $payments = $account->payments()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $transactions = $billings->merge($payments);

        // Determine current and previous meter readings
        $latestReading = $meter->readings()
            ->orderBy('reading_date', 'desc')
            ->first();

        $currentMeterReading = $latestReading?->reading ?? null;

        $previousMeterReading = $meter->readings()
            ->orderBy('reading_date', 'desc')
            ->skip(1)
            ->first()?->reading ?? null;

        $pdf = Pdf::loadView('pdf.statement', [
            'transactions' => $transactions,
            'carryForward' => $carryForward,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'resident' => $resident,
            'meter' => $meter,
            'account' => $account,  // Add account to view
            'current_meter_reading' => $currentMeterReading,
            'previous_meter_reading' => $previousMeterReading,
        ]);

        // Get latest billing for this meter
        $billing = $account->billings()
            ->whereHas('details', function ($query) use ($meter) {
                $query->where('meter_id', $meter->id);
            })
            ->latest()
            ->first();

        if (!$billing) {
            return back()->with('error', 'No billing found for this meter.');
        }

        $details = $billing->details()->where('meter_id', $meter->id)->first();

        // Calculate totals for this account
        $total_billed = $account->billings()->sum('total_amount');  // FIXED
        $total_paid = $account->payments()
            ->where('status', 'completed')
            ->sum('amount');
        $balance_due = $total_billed - $total_paid;

        // Send the email
        $email = new BillingStatement(
            $resident,
            $meter,
            $billing,
            $details,
            $total_billed,
            $total_paid,
            $balance_due
        );

        $pdf_filename = 'statement-' . $resident->name . '-' . $endDate . '.pdf';
        $email->attachData(
            $pdf->output(),
            $pdf_filename,
            ['mime' => 'application/pdf']
        );

        Mail::to($resident->email)->send($email);

        return Redirect::back()->with('status', 'Statement sent successfully!');
    }

    public function downloadAllStatements()
    {
        @set_time_limit(300);
        $combinedPdf = new Fpdi();
        $processed = 0;

        // Eager load all necessary relationships
        Meter::where('status', 'active')
            ->with([
                'resident',
                'account.billings.details',
                'account.payments',
                'readings' => function ($query) {
                    $query->orderBy('reading_date', 'desc')->limit(2);
                }
            ])
            ->chunkById(25, function ($meters) use (&$combinedPdf, &$processed) {
                foreach ($meters as $meter) {
                    if (!$meter->resident || !$meter->resident->email || !$meter->account) {
                        continue;
                    }

                    $account = $meter->account;
                    $startDate = now()->startOfYear()->toDateString();
                    $endDate = now()->toDateString();

                    // Calculate carry forward (from preloaded data)
                    $carryForwardBillings = $account->billings
                        ->where('created_at', '<', $startDate)
                        ->whereNotIn('status', ['voided'])
                        ->sum('total_amount');  // FIXED

                    $carryForwardPayments = $account->payments
                        ->where('created_at', '<', $startDate)
                        ->where('status', 'completed')
                        ->sum('amount');

                    $carryForward = $carryForwardBillings - $carryForwardPayments;

                    // Get transactions (from preloaded data)
                    $billings = $account->billings
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->whereNotIn('status', ['voided']);

                    $payments = $account->payments
                        ->whereBetween('created_at', [$startDate, $endDate]);

                    $transactions = $billings->merge($payments)
                        ->sortBy('created_at');

                    // Get meter readings (from preloaded collection)
                    $currentMeterReading = null;
                    $previousMeterReading = null;

                    if ($meter->readings->isNotEmpty()) {
                        $currentMeterReading = $meter->readings->first()->reading ?? null;
                        if ($meter->readings->count() > 1) {
                            $previousMeterReading = $meter->readings->skip(1)->first()->reading ?? null;
                        }
                    }

                    // Generate PDF for this meter
                    $pdf = Pdf::loadView('pdf.statement', [
                        'transactions' => $transactions,
                        'carryForward' => $carryForward,
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'resident' => $meter->resident,
                        'meter' => $meter,
                        'account' => $account,
                        'current_meter_reading' => $currentMeterReading,
                        'previous_meter_reading' => $previousMeterReading,
                    ]);

                    // Add to combined PDF
                    $tempFile = tempnam(sys_get_temp_dir(), 'stmt_');
                    file_put_contents($tempFile, $pdf->output());

                    $pageCount = $combinedPdf->setSourceFile($tempFile);
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $templateId = $combinedPdf->importPage($pageNo);
                        $combinedPdf->AddPage();
                        $combinedPdf->useTemplate($templateId);
                    }

                    unlink($tempFile);
                    $processed++;
                }
            });

        if ($processed === 0) {
            return back()->with('error', 'No statements generated. Check if meters have residents with emails.');
        }

        // Output combined PDF
        $filename = 'all-statements-' . now()->format('Y-m-d') . '.pdf';
        return Response::make($combinedPdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    public function readingList()
    {
        $meters = Meter::with(['resident', 'account', 'readings' => function ($query) {
            $query->latest()->limit(1);
        }])
            ->where('status', 'active')
            ->get();

        $pdf = Pdf::loadView('pdf.meter_reading_list', compact('meters'));

        return $pdf->download('meter_reading_list_' . now()->format('Y-m-d') . '.pdf');
    }
}
