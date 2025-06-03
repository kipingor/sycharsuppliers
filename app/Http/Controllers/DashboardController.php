<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
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

        $totalResidents = Resident::count();
        $activeMeters = Meter::where('status', 'active')->count();

        $totalRevenue = Payment::whereBetween('created_at', [$startDate, $endDate])->sum('amount');
        $pendingPayments = Billing::where('status', 'pending')->sum('amount_due');
        $overdueBillsCount = Billing::where('status', 'overdue')->count();

        $monthlyRevenue = Payment::selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, SUM(amount) as total')
            ->whereBetween('created_at', [$startDate->subMonths(11)->startOfMonth(), $endDate->endOfMonth()])
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->month => $item->total];
            });

        $yearlyConsumption = MeterReading::selectRaw('YEAR(reading_date) as year, SUM(reading_value) as total')
            ->whereBetween('reading_date', [$startDate->copy()->subYears(5), $endDate])
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('total', 'year');

        $data = [
            'user' => Auth::user(),
            'initialMetrics' => [
                'totalResidents' => $totalResidents,
                'activeMeters' => $activeMeters,
                'totalRevenue' => $totalRevenue,
                'pendingPayments' => $pendingPayments,
                'overdueBillsCount' => $overdueBillsCount,
            ],
            'initialChartData' => [
                'monthlyRevenue' => $monthlyRevenue,
                'yearlyConsumption' => $yearlyConsumption,
            ]
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'metrics' => $data['initialMetrics'],
                'chartData' => $data['initialChartData']
            ]);
        }

        return Inertia::render('dashboard', $data);
    }
}
