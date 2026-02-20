import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import * as React from 'react';
import { Progress } from "@/components/ui/progress"
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import MetricCard from '@/components/ui/metric-card';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select'; // Corrected casing
import { Button } from "@/components/ui/button";
import { type User } from '@/types';
import { Download } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, XAxis } from "recharts"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  ChartConfig,
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from "@/components/ui/chart"
import { formatCurrency } from '@/lib/utils';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

type Metrics = {
    totalResidents: number;
    activeMeters: number;
    totalRevenue: number;
    pendingPayments: number;
    overdueBillsCount: number;
};

type ChartData = {
    monthlyRevenue: Record<string, number>;
    yearlyConsumption: Record<string, number>;
};

interface DashboardProps {
    user: User;
    metrics: Metrics;
    chartData: ChartData;
    period: string;
    can: {
        downloadReadingList: boolean;
    };
}

export default function Dashboard() {
    const {
        user,
        metrics,
        chartData,
        period,
        can,
    } = usePage<SharedData & DashboardProps>().props;

    const chartConfig = {
        monthlyRevenue: {
            label: "Monthly Revenue",
            color: "hsl(var(--chart-1))",
        },
        yearlyConsumption: {
            label: "Yearly Consumption",
            color: "hsl(var(--chart-2))",
        },
    } satisfies ChartConfig

    const [activeChart, setActiveChart] = React.useState<keyof typeof chartConfig>('monthlyRevenue');
    const [progress, setProgress] = React.useState(13);
    const changePeriod = (value: string) => {
        router.get(
            route('dashboard'),
            { period: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const downloadReadingList = () => {        
        router.get(route('meters.reading-list.download'), {}, {
            preserveState: true,
        });
    };
    //     const blob = await response.blob();
    //     const url = window.URL.createObjectURL(blob);
    //     const link = document.createElement('a');
    //     link.href = url;
    //     link.setAttribute('download', 'reading-list.pdf');
    //     document.body.appendChild(link);
    //     link.click();
    //     if (link.parentNode) {
    //         link.parentNode.removeChild(link);
    //     }
    // };    

    const total = React.useMemo(
        () => ({
            monthlyRevenue: Object.values(chartData.monthlyRevenue).reduce((acc, curr) => acc + curr, 0),
            yearlyConsumption: 0, //Object.values(chartData.yearlyConsumption).reduce((acc, curr) => acc + curr, 0),
        }),
        [chartData]
    );

    React.useEffect(() => {
        const timer = setTimeout(() => setProgress(100), 500)
        return () => clearTimeout(timer)
      }, [])

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
                    <div className="flex gap-2">
                        <Select value={period} onValueChange={changePeriod}>
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
                        {can.downloadReadingList && (
                            <Button
                                onClick={downloadReadingList}
                                className="flex items-center gap-2"                        
                            >
                                <Download size={18} />
                                Download Reading List
                            </Button>
                        )}
                    </div>
                </div>
                {/* Metrics Cards */}
                <div className="grid gap-8 mt-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard title="Total Residents" value={metrics.totalResidents} />
                    <MetricCard title="Active Meters" value={metrics.activeMeters} />
                    <MetricCard 
                        title="Total Revenue"
                        value={formatCurrency(metrics.totalRevenue)} 
                    />
                    <MetricCard 
                        title="Pending Payments" 
                        value={formatCurrency(metrics.pendingPayments)}
                    />
                    {/*
                    <MetricCard 
                        title="Overdue Bills" 
                        value={metrics.overdueBillsCount} 
                    />
                    */}
                </div>
                
                {/* Charts */}

                <Card className="mt-10">
                    <CardHeader className="flex flex-col items-stretch space-y-0 border-b p-0 sm:flex-row">
                        <div className="flex flex-1 flex-col justify-center gap-1 px-6 py-5 sm:py-6">
                            <CardTitle>Revenue & Consumption Trends</CardTitle>
                            <CardDescription>
                                Showing total visitors for the last 3 months
                            </CardDescription>
                        </div>
                        <div className="flex">
                            {["monthlyRevenue", "yearlyConsumption"].map((key) => {
                                const chart = key as keyof typeof chartConfig
                                return (
                                    <button
                                        key={chart}
                                        data-active={activeChart === chart}
                                        className="relative z-30 flex flex-1 flex-col justify-center gap-1 border-t px-6 py-4 text-left even:border-l data-[active=true]:bg-muted/50 sm:border-l sm:border-t-0 sm:px-8 sm:py-6"
                                        onClick={() => setActiveChart(chart)}
                                    >
                                        <span className="text-xs text-muted-foreground">
                                            {chartConfig[chart].label}
                                        </span>
                                        <span className="text-lg font-bold leading-none sm:text-3xl">
                                            {chart === 'monthlyRevenue' 
                                                ? formatCurrency(total[key as keyof typeof total] || 0)
                                                : total[key as keyof typeof total] || 0
                                            }
                                        </span>
                                    </button>
                                )
                            })}
                        </div>
                    </CardHeader>
                    <CardContent className="px-2 sm:p-6">
                        <ChartContainer config={chartConfig} className="aspect-auto h-[250px] w-full">
                            <BarChart accessibilityLayer data={Object.entries(chartData[activeChart]).map(([key, value]) => ({
                                name: activeChart === 'monthlyRevenue' 
                                    ? new Date(new Date().getFullYear(), parseInt(key) - 1).toLocaleString('default', { month: 'short' })
                                    : key,
                                value: value
                            }))} margin={{
                                left: 12,
                                right: 12,
                            }}>
                                <CartesianGrid vertical={false} />
                                <XAxis 
                                    dataKey="name" 
                                    tickLine={false} 
                                    axisLine={false} 
                                    tickMargin={8} 
                                    minTickGap={32}
                                    // angle={-45}
                                    textAnchor="end"
                                />
                                <ChartTooltip
                                    content={
                                        <ChartTooltipContent
                                            className='w-[150px]'
                                            nameKey='value'
                                            labelFormatter={(value) => activeChart === 'monthlyRevenue' 
                                                ? formatCurrency(value)
                                                : value.toString()
                                            }
                                        />
                                    }
                                />
                                <Bar dataKey="value" fill={chartConfig[activeChart].color} />
                            </BarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
