<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Account;
use App\Models\Resident;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Billing;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', 'last_month');

        [$startDate, $endDate] = $this->getPeriodDates($period);

        return Inertia::render('dashboard', [
            'user' => $request->user(),
            'period' => $period,
            'metrics' => $this->getMetrics($startDate, $endDate),
            'chartData' => $this->getChartData(),
            'recentActivity' => $this->getRecentActivity(),
            'accountSummary' => $this->getAccountSummary(),
            'can' => [
                'downloadReadingList' => $request->user()->can('export', MeterReading::class),
            ],
        ]);
    }

    protected function getPeriodDates(string $period): array
    {
        $endDate = now()->endOfDay();
        $startDate = match ($period) {
            'last_two'     => now()->subWeeks(2)->startOfWeek(),
            'last_month'   => now()->subMonth()->startOfMonth(),
            'last_quarter' => now()->subMonths(3)->startOfMonth(),
            default        => now()->subWeek()->startOfWeek(),
        };
        return [$startDate, $endDate];
    }

    protected function getMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $totalAccounts = Account::count();
        $activeAccounts = Account::active()->count();
        $activeMeters = Meter::where('status', 'active')->count();
        $totalResidents = Resident::count();

        $totalRevenue = Payment::where('status', 'completed')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        $pendingBilled = Billing::whereIn('status', ['pending', 'partially_paid'])->sum('amount_due');
        $overdueBillsCount = Billing::where('status', 'pending')
            ->where('due_date', '<', now())->count();
        $overdueAmount = Billing::where('status', 'pending')
            ->where('due_date', '<', now())->sum('amount_due');

        $readingsThisMonth = MeterReading::whereYear('reading_date', now()->year)
            ->whereMonth('reading_date', now()->month)->count();
        $metersUnread = $activeMeters - $readingsThisMonth;

        $collectionRate = $pendingBilled > 0
            ? round(($totalRevenue / max(1, $totalRevenue + $pendingBilled)) * 100, 1)
            : 100;

        return [
            'totalAccounts'   => $totalAccounts,
            'activeAccounts'  => $activeAccounts,
            'activeMeters'    => $activeMeters,
            'totalResidents'  => $totalResidents,
            'totalRevenue'    => $totalRevenue,
            'pendingAmount'   => $pendingBilled,
            'overdueCount'    => $overdueBillsCount,
            'overdueAmount'   => $overdueAmount,
            'readingsThisMonth' => $readingsThisMonth,
            'metersUnread'    => max(0, $metersUnread),
            'collectionRate'  => $collectionRate,
        ];
    }

    protected function getChartData(): array
    {
        // Monthly revenue for last 12 months
        $monthlyRevenue = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('M Y');
            $monthlyRevenue[$key] = Payment::where('status', 'completed')
                ->whereYear('payment_date', $month->year)
                ->whereMonth('payment_date', $month->month)
                ->sum('amount');
        }

        // Monthly billing vs collection for last 6 months
        $billingVsCollection = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $label = $month->format('M Y');
            $billed = Billing::whereYear('issued_at', $month->year)
                ->whereMonth('issued_at', $month->month)
                ->sum('total_amount');
            $collected = Payment::where('status', 'completed')
                ->whereYear('payment_date', $month->year)
                ->whereMonth('payment_date', $month->month)
                ->sum('amount');
            $billingVsCollection[$label] = ['billed' => $billed, 'collected' => $collected];
        }

        return [
            'monthlyRevenue'      => $monthlyRevenue,
            'billingVsCollection' => $billingVsCollection,
        ];
    }

    protected function getRecentActivity(): array
    {
        $recentBills = Billing::with('account:id,name,account_number')
            ->latest('issued_at')
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'type'    => 'bill',
                'id'      => $b->id,
                'label'   => "Bill #{$b->id} — " . optional($b->account)->name,
                'amount'  => $b->total_amount,
                'status'  => $b->status,
                'date'    => $b->issued_at->format('d M Y'),
                'link'    => route('billings.show', $b->id),
            ]);

        $recentPayments = Payment::with('account:id,name,account_number')
            ->latest('payment_date')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'type'    => 'payment',
                'id'      => $p->id,
                'label'   => "Payment from " . optional($p->account)->name,
                'amount'  => $p->amount,
                'status'  => $p->status,
                'date'    => Carbon::parse($p->payment_date)->format('d M Y'),
                'link'    => route('payments.show', $p->id),
            ]);

        return $recentBills->concat($recentPayments)
            ->sortByDesc('date')
            ->take(8)
            ->values()
            ->toArray();
    }

    protected function getAccountSummary(): array
    {
        $statusCounts = Account::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'active'    => $statusCounts['active'] ?? 0,
            'suspended' => $statusCounts['suspended'] ?? 0,
            'inactive'  => $statusCounts['inactive'] ?? 0,
        ];
    }
}
