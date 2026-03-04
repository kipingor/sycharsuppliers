<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Account;
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
        $endDate   = $request->input('end', Carbon::create($year)->endOfYear()->toDateString());

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
        $endDate   = $request->input('end', Carbon::create($year)->endOfYear()->toDateString());

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

    // ──────────────────────────────────────────────────────────────────────────
    //  Expense Report
    // ──────────────────────────────────────────────────────────────────────────

    public function expenseReport(Request $request)
    {
        $request->validate([
            'year'  => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
            'start' => 'nullable|date',
            'end'   => 'nullable|date|after_or_equal:start',
        ]);

        $year      = $request->input('year', date('Y'));
        $startDate = $request->input('start', Carbon::create($year)->startOfYear()->toDateString());
        $endDate   = $request->input('end', Carbon::create($year)->endOfYear()->toDateString());

        $base = Expense::whereBetween('expense_date', [$startDate, $endDate]);

        $totalAll      = (float) (clone $base)->sum('amount');
        $totalApproved = (float) (clone $base)->approved()->sum('amount');
        $totalPending  = (float) (clone $base)->pending()->sum('amount');
        $totalRejected = (float) (clone $base)->rejected()->sum('amount');

        // By category
        $byCategory = Expense::selectRaw(
            "category,
                 SUM(amount) AS total,
                 SUM(CASE WHEN status = 1 AND approved_by IS NOT NULL THEN amount ELSE 0 END) AS approved,
                 SUM(CASE WHEN status = 0 AND rejected_by IS NULL THEN amount ELSE 0 END) AS pending,
                 COUNT(*) AS count"
        )
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category,
                'total'    => (float) $r->total,
                'approved' => (float) $r->approved,
                'pending'  => (float) $r->pending,
                'count'    => (int)   $r->count,
            ])
            ->toArray();

        // Monthly breakdown
        $monthly = Expense::selectRaw(
            "DATE_FORMAT(expense_date, '%Y-%m') AS month,
                 SUM(amount) AS total,
                 SUM(CASE WHEN status = 1 AND approved_by IS NOT NULL THEN amount ELSE 0 END) AS approved,
                 COUNT(*) AS count"
        )
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'month'    => $r->month,
                'label'    => Carbon::createFromFormat('Y-m', $r->month)->format('M Y'),
                'total'    => (float) $r->total,
                'approved' => (float) $r->approved,
                'count'    => (int)   $r->count,
            ])
            ->toArray();

        return Inertia::render('reports/expense', [
            'summary' => [
                'total_all'      => $totalAll,
                'total_approved' => $totalApproved,
                'total_pending'  => $totalPending,
                'total_rejected' => $totalRejected,
                'expense_count'  => (clone $base)->count(),
            ],
            'by_category' => $byCategory,
            'monthly'     => $monthly,
            'year'        => (int) $year,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'years'       => $this->availableYears(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  P&L Report
    // ──────────────────────────────────────────────────────────────────────────

    public function plReport(Request $request)
    {
        $request->validate([
            'year'  => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
            'start' => 'nullable|date',
            'end'   => 'nullable|date|after_or_equal:start',
        ]);

        $year      = $request->input('year', date('Y'));
        $startDate = $request->input('start', Carbon::create($year)->startOfYear()->toDateString());
        $endDate   = $request->input('end', Carbon::create($year)->endOfYear()->toDateString());

        $totalRevenue  = (float) Billing::whereBetween('issued_at', [$startDate, $endDate . ' 23:59:59'])
            ->whereNotIn('status', ['voided'])->sum('total_amount');

        $cashCollected = (float) Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount');

        $totalExpenses = (float) Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->approved()->sum('amount');

        // Monthly P&L
        $monthlyRevenue  = Billing::selectRaw("DATE_FORMAT(issued_at,'%Y-%m') AS month, SUM(total_amount) AS amount")
            ->whereBetween('issued_at', [$startDate, $endDate . ' 23:59:59'])
            ->whereNotIn('status', ['voided'])
            ->groupBy('month')->orderBy('month')->pluck('amount', 'month');

        $monthlyExpenses = Expense::selectRaw("DATE_FORMAT(expense_date,'%Y-%m') AS month, SUM(amount) AS amount")
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->approved()
            ->groupBy('month')->orderBy('month')->pluck('amount', 'month');

        $monthlyPayments = Payment::selectRaw("DATE_FORMAT(payment_date,'%Y-%m') AS month, SUM(amount) AS amount")
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->groupBy('month')->orderBy('month')->pluck('amount', 'month');

        $allMonths = collect($monthlyRevenue->keys())
            ->merge($monthlyExpenses->keys())
            ->unique()->sort()->values();

        $monthly = $allMonths->map(fn ($m) => [
            'month'       => $m,
            'label'       => Carbon::createFromFormat('Y-m', $m)->format('M Y'),
            'revenue'     => (float) ($monthlyRevenue[$m] ?? 0),
            'expenses'    => (float) ($monthlyExpenses[$m] ?? 0),
            'cash_in'     => (float) ($monthlyPayments[$m] ?? 0),
            'net_profit'  => (float) ($monthlyRevenue[$m] ?? 0) - (float) ($monthlyExpenses[$m] ?? 0),
        ])->values()->toArray();

        return Inertia::render('reports/pl', [
            'summary' => [
                'total_revenue'       => $totalRevenue,
                'cash_collected'      => $cashCollected,
                'total_expenses'      => $totalExpenses,
                'gross_profit'        => $totalRevenue - $totalExpenses,
                'profit_margin'       => $totalRevenue > 0
                    ? round((($totalRevenue - $totalExpenses) / $totalRevenue) * 100, 1)
                    : 0,
                'outstanding_revenue' => $totalRevenue - $cashCollected,
            ],
            'monthly'    => $monthly,
            'year'       => (int) $year,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'years'      => $this->availableYears(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  AR Aging Report
    // ──────────────────────────────────────────────────────────────────────────

    public function agingReport(Request $request)
    {
        $today = now()->toDateString();

        $bills = Billing::with('account:id,name,account_number,phone,email')
            ->whereNotIn('status', ['paid', 'voided'])
            ->where('amount_due', '>', 0)
            ->orderBy('due_date')
            ->get();

        $buckets = [
            'current'  => ['label' => 'Current (not yet due)',      'min' => null, 'max' => 0,  'amount' => 0, 'count' => 0],
            '1_30'     => ['label' => '1 – 30 days',                'min' => 1,   'max' => 30,  'amount' => 0, 'count' => 0],
            '31_60'    => ['label' => '31 – 60 days',               'min' => 31,  'max' => 60,  'amount' => 0, 'count' => 0],
            '61_90'    => ['label' => '61 – 90 days',               'min' => 61,  'max' => 90,  'amount' => 0, 'count' => 0],
            '90_plus'  => ['label' => 'Over 90 days',               'min' => 91,  'max' => null, 'amount' => 0, 'count' => 0],
        ];

        $accountRows = [];

        foreach ($bills as $bill) {
            $daysOverdue = max(0, now()->diffInDays($bill->due_date, false) * -1);
            $daysOverdue = (int) $daysOverdue;
            $balance     = (float) $bill->amount_due;

            $bucket = match (true) {
                $daysOverdue <= 0  => 'current',
                $daysOverdue <= 30 => '1_30',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default            => '90_plus',
            };

            $buckets[$bucket]['amount'] += $balance;
            $buckets[$bucket]['count']++;

            $accId = $bill->account_id;
            if (!isset($accountRows[$accId])) {
                $accountRows[$accId] = [
                    'account_id'     => $accId,
                    'account_name'   => $bill->account->name ?? 'Unknown',
                    'account_number' => $bill->account->account_number ?? '',
                    'phone'          => $bill->account->phone ?? '',
                    'current'        => 0,
                    '1_30'           => 0,
                    '31_60'          => 0,
                    '61_90'          => 0,
                    '90_plus'        => 0,
                    'total'          => 0,
                ];
            }

            $accountRows[$accId][$bucket] += $balance;
            $accountRows[$accId]['total']  += $balance;
        }

        usort($accountRows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return Inertia::render('reports/aging', [
            'buckets'      => array_values($buckets),
            'accounts'     => array_values($accountRows),
            'grand_total'  => collect($buckets)->sum('amount'),
            'generated_at' => now()->format('d M Y H:i'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Debtors Report
    // ──────────────────────────────────────────────────────────────────────────

    public function debtorsReport(Request $request)
    {
        $minBalance = (float) $request->input('min_balance', 0);

        $accounts = Account::with(['billings', 'payments'])
            ->whereHas('billings', function ($q) {
                $q->whereNotIn('status', ['paid', 'voided'])->where('amount_due', '>', 0);
            })
            ->get()
            ->map(function (Account $account) {
                $outstanding = (float) $account->billings()
                    ->whereNotIn('status', ['paid', 'voided'])
                    ->sum('amount_due');

                $lastPayment = $account->payments()->latest('payment_date')->first();

                $oldestBill = $account->billings()
                    ->whereNotIn('status', ['paid', 'voided'])
                    ->orderBy('due_date')
                    ->first();

                $daysOverdue = $oldestBill
                    ? max(0, (int) now()->diffInDays($oldestBill->due_date, false) * -1)
                    : 0;

                return [
                    'account_id'          => $account->id,
                    'account_number'      => $account->account_number,
                    'name'                => $account->name,
                    'phone'               => $account->phone,
                    'email'               => $account->email,
                    'address'             => $account->address,
                    'outstanding_balance' => $outstanding,
                    'bill_count'          => $account->billings()
                        ->whereNotIn('status', ['paid', 'voided'])->count(),
                    'last_payment_date'   => $lastPayment?->payment_date?->format('Y-m-d'),
                    'last_payment_amount' => $lastPayment ? (float) $lastPayment->amount : null,
                    'days_overdue'        => $daysOverdue,
                    'status'              => $account->status,
                ];
            })
            ->filter(fn ($a) => $a['outstanding_balance'] >= $minBalance)
            ->sortByDesc('outstanding_balance')
            ->values()
            ->toArray();

        return Inertia::render('reports/debtors', [
            'debtors'      => $accounts,
            'grand_total'  => collect($accounts)->sum('outstanding_balance'),
            'total_count'  => count($accounts),
            'min_balance'  => $minBalance,
            'generated_at' => now()->format('d M Y H:i'),
        ]);
    }

    private function availableYears(): array
    {
        $earliest = Billing::min(DB::raw('YEAR(issued_at)')) ?? date('Y');
        $latest   = date('Y');

        return range($latest, (int) $earliest);
    }
}
