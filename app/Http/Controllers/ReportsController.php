<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportsController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    //  Reports index
    // ──────────────────────────────────────────────────────────────────────────
    public function index()
    {
        return Inertia::render('reports/reports');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Tax report – page
    // ──────────────────────────────────────────────────────────────────────────
    public function taxReport(Request $request)
    {
        $request->validate([
            'year'     => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
            'start'    => 'nullable|date',
            'end'      => 'nullable|date|after_or_equal:start',
        ]);

        $year      = $request->input('year', date('Y'));
        $startDate = $request->input('start', Carbon::create($year)->startOfYear()->toDateString());
        $endDate   = $request->input('end',   Carbon::create($year)->endOfYear()->toDateString());

        $summary   = $this->buildTaxSummary($startDate, $endDate);

        return Inertia::render('reports/tax', [
            'summary'    => $summary,
            'year'       => (int) $year,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'years'      => $this->availableYears(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Tax report – PDF download
    // ──────────────────────────────────────────────────────────────────────────
    public function downloadTaxReport(Request $request)
    {
        $request->validate([
            'year'  => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
            'start' => 'nullable|date',
            'end'   => 'nullable|date|after_or_equal:start',
        ]);

        $year      = $request->input('year', date('Y'));
        $startDate = $request->input('start', Carbon::create($year)->startOfYear()->toDateString());
        $endDate   = $request->input('end',   Carbon::create($year)->endOfYear()->toDateString());

        $summary = $this->buildTaxSummary($startDate, $endDate);

        $company = [
            'name'    => Config::get('app.company_name', config('app.name', 'Sychar Suppliers')),
            'logo'    => Config::get('app.company_logo', null),
            'address' => Config::get('app.company_address', null),
            'phone'   => Config::get('app.company_phone', null),
            'email'   => Config::get('app.company_email', null),
            'pin'     => Config::get('app.company_tax_pin', null),   // KRA PIN / Tax ID
        ];

        $data = [
            'summary'      => $summary,
            'company'      => $company,
            'year'         => $year,
            'start_date'   => Carbon::parse($startDate)->format('d M Y'),
            'end_date'     => Carbon::parse($endDate)->format('d M Y'),
            'generated_at' => Carbon::now(),
        ];

        return Pdf::loadView('reports.tax-pdf', $data)
                  ->setPaper('a4', 'portrait')
                  ->download("tax_report_{$year}_{$startDate}_to_{$endDate}.pdf");
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Core data builder
    // ──────────────────────────────────────────────────────────────────────────
    private function buildTaxSummary(string $startDate, string $endDate): array
    {
        // ── 1. Revenue from bills issued in period ────────────────────────────
        $billsQuery = Billing::whereBetween('issued_at', [$startDate, $endDate . ' 23:59:59'])
                             ->whereNotIn('status', ['voided']);

        $totalBilled  = (clone $billsQuery)->sum('total_amount');
        $totalBillCount = (clone $billsQuery)->count();

        // ── 2. Cash collected in period (payments) ────────────────────────────
        $paymentsQuery = Payment::whereBetween('payment_date', [$startDate, $endDate . ' 23:59:59']);

        $totalCollected   = (clone $paymentsQuery)->sum('amount');
        $totalPaymentCount = (clone $paymentsQuery)->count();

        // ── 3. Outstanding receivables at end of period ───────────────────────
        $outstandingBalance = Billing::where('due_date', '<=', $endDate)
                                     ->whereNotIn('status', ['paid', 'voided'])
                                     ->sum('amount_due');

        // ── 4. Monthly breakdown ──────────────────────────────────────────────
        $monthlyBilled = Billing::selectRaw(
                "DATE_FORMAT(issued_at, '%Y-%m') AS month,
                 SUM(total_amount) AS billed,
                 COUNT(*) AS bill_count"
            )
            ->whereBetween('issued_at', [$startDate, $endDate . ' 23:59:59'])
            ->whereNotIn('status', ['voided'])
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthlyCollected = Payment::selectRaw(
                "DATE_FORMAT(payment_date, '%Y-%m') AS month,
                 SUM(amount) AS collected,
                 COUNT(*) AS payment_count"
            )
            ->whereBetween('payment_date', [$startDate, $endDate . ' 23:59:59'])
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Merge months
        $allMonths = $monthlyBilled->keys()
                        ->merge($monthlyCollected->keys())
                        ->unique()
                        ->sort()
                        ->values();

        $monthly = $allMonths->map(function ($month) use ($monthlyBilled, $monthlyCollected) {
            $b = $monthlyBilled->get($month);
            $c = $monthlyCollected->get($month);

            return [
                'month'         => $month,
                'label'         => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'billed'        => (float) ($b?->billed ?? 0),
                'bill_count'    => (int)   ($b?->bill_count ?? 0),
                'collected'     => (float) ($c?->collected ?? 0),
                'payment_count' => (int)   ($c?->payment_count ?? 0),
                'variance'      => (float) ($b?->billed ?? 0) - (float) ($c?->collected ?? 0),
            ];
        })->values()->toArray();

        // ── 5. Payment method breakdown ───────────────────────────────────────
        $byMethod = Payment::selectRaw('method, SUM(amount) AS total, COUNT(*) AS count')
            ->whereBetween('payment_date', [$startDate, $endDate . ' 23:59:59'])
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'method' => ucfirst($r->method),
                'total'  => (float) $r->total,
                'count'  => (int)   $r->count,
            ])
            ->toArray();

        // ── 6. Status breakdown of bills ──────────────────────────────────────
        $byStatus = Billing::selectRaw('status, COUNT(*) AS count, SUM(total_amount) AS total')
            ->whereBetween('issued_at', [$startDate, $endDate . ' 23:59:59'])
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status,
                'count'  => (int)   $r->count,
                'total'  => (float) $r->total,
            ])
            ->toArray();

        // ── 7. Collection rate ────────────────────────────────────────────────
        $collectionRate = $totalBilled > 0
            ? round(($totalCollected / $totalBilled) * 100, 2)
            : 0;

        return [
            'total_billed'        => (float) $totalBilled,
            'total_bill_count'    => (int)   $totalBillCount,
            'total_collected'     => (float) $totalCollected,
            'total_payment_count' => (int)   $totalPaymentCount,
            'outstanding_balance' => (float) $outstandingBalance,
            'collection_rate'     => $collectionRate,
            'monthly'             => $monthly,
            'by_method'           => $byMethod,
            'by_status'           => $byStatus,
        ];
    }

    private function availableYears(): array
    {
        $earliest = Billing::min(DB::raw('YEAR(issued_at)')) ?? date('Y');
        $latest   = date('Y');

        return range($latest, (int) $earliest);
    }
}