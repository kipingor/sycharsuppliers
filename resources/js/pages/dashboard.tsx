import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { useState, useEffect } from 'react';
import Chart from 'chart.js/auto';
import axios from 'axios';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import MetricCard from '@/components/ui/metric-card';
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'; // Corrected casing
import { type User } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
];

type Metrics = {
  totalCustomers: number;
  activeMeters: number;
  totalRevenue: number;
  pendingPayments: number;
  overdueBillsCount: number;
};

type ChartData = {
  monthlyRevenue: Record<string, number>;
  yearlyConsumption: Record<string, number>;
};

export default function Dashboard({ user, initialMetrics, initialChartData }: { user: User; initialMetrics: Metrics; initialChartData: ChartData }) {
  const [period, setPeriod] = useState<string>('last_week');
  const [metrics, setMetrics] = useState<Metrics>(initialMetrics);
  const [chartData, setChartData] = useState<ChartData>(initialChartData);
  const [loading, setLoading] = useState<boolean>(false);

  const fetchMetrics = async (selectedPeriod: string) => {
    setLoading(true);
    try {
      const response = await axios.get(route('dashboard'), { params: { period: selectedPeriod } });
      setMetrics(response.data.metrics || initialMetrics);
      setChartData(response.data.chartData || initialChartData);
    } catch (error) {
      console.error('Error fetching data:', error);
    } finally {
      setLoading(false);
    }
  };

  // Fetch data when the period changes
  useEffect(() => {
    fetchMetrics(period);
  }, [period]);

  // Update charts dynamically
  useEffect(() => {
    const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart') as HTMLCanvasElement;
    const yearlyConsumptionCtx = document.getElementById('yearlyConsumptionChart') as HTMLCanvasElement;

    if (monthlyRevenueCtx && yearlyConsumptionCtx) {
      const monthlyRevenueChart = new Chart(monthlyRevenueCtx, {
        type: 'line',
        data: {
          labels: Object.keys(chartData.monthlyRevenue),
          datasets: [{
            label: 'Monthly Revenue',
            data: Object.values(chartData.monthlyRevenue),
            borderColor: 'blue',
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderWidth: 2,
          }],
        },
        options: { responsive: true, maintainAspectRatio: false },
      });

      const yearlyConsumptionChart = new Chart(yearlyConsumptionCtx, {
        type: 'line',
        data: {
          labels: Object.keys(chartData.yearlyConsumption),
          datasets: [{
            label: 'Yearly Consumption',
            data: Object.values(chartData.yearlyConsumption),
            borderColor: 'green',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderWidth: 2,
          }],
        },
        options: { responsive: true, maintainAspectRatio: false },
      });

      return () => {
        monthlyRevenueChart.destroy();
        yearlyConsumptionChart.destroy();
      };
    }
  }, [chartData]);

  if (loading) return <div>Loading...</div>;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard" />
      <div className="mx-auto max-w-6xl">
        <h1 className="text-2xl font-semibold text-zinc-950 dark:text-white">
          Good {new Date().getHours() < 12 ? 'morning' : new Date().getHours() < 17 ? 'afternoon' : new Date().getHours() < 20 ? 'evening' : 'night'}, {user.name}
        </h1>

        {/* Period Selection */}
        <div className="mt-8 flex items-end justify-between">
          <h2 className="text-base font-semibold text-zinc-950 dark:text-white">Overview</h2>
          <div className="w-48">
            <Select value={period} onValueChange={setPeriod}>
              <SelectTrigger>
                <SelectValue placeholder="Select period" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="last_week">Last week</SelectItem>
                <SelectItem value="last_two">Last two weeks</SelectItem>
                <SelectItem value="last_month">Last month</SelectItem>
                <SelectItem value="last_quarter">Last quarter</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        {/* Metrics Cards */}
        <div className="grid gap-8 mt-4 sm:grid-cols-2 xl:grid-cols-4">
          <MetricCard title="Total Customers" value={metrics.totalCustomers} />
          <MetricCard title="Active Meters" value={metrics.activeMeters} />
          <MetricCard 
            title="Total Revenue" 
            value={`KES ${metrics.totalRevenue.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`} 
          />
          <MetricCard 
            title="Pending Payments" 
            value={`KES ${metrics.pendingPayments.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`} 
          />
          <MetricCard 
            title="Overdue Bills" 
            value={metrics.overdueBillsCount} 
          />
        </div>

        {/* Charts */}
        <div className="bg-white dark:bg-gray-800 p-6 rounded-lg shadow my-6">
          <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
            Revenue & Consumption Trends
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 my-6">
            <div className="max-w-full">
              <h4 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
                Monthly Revenue
              </h4>
              <div className="w-full max-w-lg mx-auto">
                <canvas id="monthlyRevenueChart" className="w-full h-64"></canvas>
              </div>
            </div>
            <div className="max-w-full">
              <h4 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
                Yearly Consumption
              </h4>
              <div className="w-full max-w-lg mx-auto">
                <canvas id="yearlyConsumptionChart" className="w-full h-64"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
