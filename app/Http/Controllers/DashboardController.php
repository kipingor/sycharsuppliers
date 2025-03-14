<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', 'last_week');

        switch ($period) {
            case 'last_two':
                $startDate = now()->subWeeks(2)->startOfWeek();
                break;
            case 'last_month':
                $startDate = now()->subMonth()->startOfMonth();
                break;
            case 'last_quarter':
                $startDate = now()->subMonths(3)->startOfMonth();
                break;
            default: // 'last_week'
                $startDate = now()->subWeek()->startOfWeek();
                break;
        }
        $endDate = now()->endOfDay();

        $totalCustomers = Customer::git unt();
        $activeMeters = Meter::where('status', 'active')->count();

        $totalRevenue = Payment::whereBetween('created_at', [$startDate, $endDate])->sum('amount');
        $pendingPayments = Payment::where('status', 'pending')->sum('amount');
        $overdueBillsCount = Payment::where('status', 'overdue')->count();

        $monthlyRevenue = Payment::selectRaw('MONTH(created_at) as month, SUM(amount) as total')
            ->whereBetween('created_at', [$startDate->copy()->subYear(), $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $yearlyConsumption = MeterReading::selectRaw('YEAR(reading_date) as year, SUM(reading_value) as total')
            ->whereBetween('reading_date', [$startDate->copy()->subYears(5), $endDate])
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('total', 'year');

        return Inertia::render('dashboard', [
            'user' => Auth::user(),
            'initialMetrics' => [
                'totalCustomers' => $totalCustomers,
                'activeMeters' => $activeMeters,
                'totalRevenue' => $totalRevenue,
                'pendingPayments' => $pendingPayments,
                'overdueBillsCount' => $overdueBillsCount,
            ],
            'initialChartData' => [
                'monthlyRevenue' => $monthlyRevenue,
                'yearlyConsumption' => $yearlyConsumption,
            ]
        ]);
    }
}
