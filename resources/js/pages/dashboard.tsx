import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import { SharedData, type BreadcrumbItem, type User } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Activity, AlertCircle, CreditCard, Download, FileText, Gauge, TrendingUp, Users } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

type Metrics = {
    totalAccounts: number;
    activeAccounts: number;
    activeMeters: number;
    totalResidents: number;
    totalRevenue: number;
    pendingAmount: number;
    overdueCount: number;
    overdueAmount: number;
    readingsThisMonth: number;
    metersUnread: number;
    collectionRate: number;
};

type ChartData = {
    monthlyRevenue: Record<string, number>;
    billingVsCollection: Record<string, { billed: number; collected: number }>;
};

type ActivityItem = {
    type: 'bill' | 'payment';
    id: number;
    label: string;
    amount: number;
    status: string;
    date: string;
    link: string;
};

type AccountSummary = { active: number; suspended: number; inactive: number };

interface DashboardProps {
    user: User;
    metrics: Metrics;
    chartData: ChartData;
    recentActivity: ActivityItem[];
    accountSummary: AccountSummary;
    period: string;
    can: {
        downloadReadingList: boolean;
        viewReports: boolean;
        createBills: boolean;
        createPayments: boolean;
        viewAccounts: boolean;
    };
}

const statusColors: Record<string, string> = {
    paid: 'bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-300',
    pending: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    partially_paid: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    completed: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    voided: 'bg-gray-100 text-gray-500',
};

export default function Dashboard() {
    const { user, metrics, chartData, recentActivity, accountSummary, period, can } = usePage<SharedData & DashboardProps>().props;

    const greeting = (() => {
        const h = new Date().getHours();
        if (h < 12) return 'Good morning';
        if (h < 17) return 'Good afternoon';
        if (h < 20) return 'Good evening';
        return 'Good night';
    })();

    const changePeriod = (value: string) => {
        router.get(
            route('dashboard'),
            { period: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const downloadReadingList = () => {
        window.location.href = route('meters.reading-list.download');
    };

    const revenueData = Object.entries(chartData.monthlyRevenue).map(([label, value]) => ({ label, value }));
    const bvcData = Object.entries(chartData.billingVsCollection).map(([label, vals]) => ({ label, ...vals }));

    const metricCards = [
        {
            title: 'Active Accounts',
            value: metrics.activeAccounts,
            sub: `${metrics.totalAccounts} total`,
            icon: <Users size={18} className="text-blue-500" />,
            color: 'border-blue-200 dark:border-blue-900/40',
        },
        {
            title: 'Active Meters',
            value: metrics.activeMeters,
            sub: metrics.metersUnread > 0 ? `${metrics.metersUnread} unread this month` : 'All read this month',
            icon: <Gauge size={18} className="text-violet-500" />,
            color: metrics.metersUnread > 0 ? 'border-violet-200 dark:border-violet-900/40' : 'border-green-200 dark:border-green-900/40',
        },
        {
            title: 'Revenue (Period)',
            value: formatCurrency(metrics.totalRevenue),
            sub: `${metrics.collectionRate}% collection rate`,
            icon: <TrendingUp size={18} className="text-green-500" />,
            color: 'border-green-200 dark:border-green-900/40',
        },
        {
            title: 'Pending Amount',
            value: formatCurrency(metrics.pendingAmount),
            sub: `${metrics.overdueCount} overdue`,
            icon: <AlertCircle size={18} className={metrics.overdueCount > 0 ? 'text-red-500' : 'text-muted-foreground'} />,
            color: metrics.overdueCount > 0 ? 'border-red-200 dark:border-red-900/40' : 'border-orange-200 dark:border-orange-900/40',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="mx-auto flex h-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-zinc-900 dark:text-white">
                            {greeting}, {user.name.split(' ')[0]} 👋
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">Here's what's happening with Sychar Water Billing.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Select value={period} onValueChange={changePeriod}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Period" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="last_week">Last week</SelectItem>
                                <SelectItem value="last_two">Last 2 weeks</SelectItem>
                                <SelectItem value="last_month">Last month</SelectItem>
                                <SelectItem value="last_quarter">Last quarter</SelectItem>
                            </SelectContent>
                        </Select>
                        {can.downloadReadingList && (
                            <Button onClick={downloadReadingList} variant="outline" className="gap-2">
                                <Download size={16} /> Reading List
                            </Button>
                        )}
                    </div>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {can.viewReports &&
                        metricCards.map((card) => (
                            <Card key={card.title} className={`border-2 ${card.color}`}>
                                <CardContent className="pt-5">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{card.title}</p>
                                            <p className="mt-1 text-2xl font-bold">{card.value}</p>
                                            <p className="text-muted-foreground mt-1 text-xs">{card.sub}</p>
                                        </div>
                                        <div className="mt-0.5">{card.icon}</div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    {can.viewReports && (
                        <>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base">Monthly Revenue</CardTitle>
                                    <CardDescription>Last 12 months collection</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ResponsiveContainer width="100%" height={200}>
                                        <BarChart data={revenueData} margin={{ left: 0, right: 8 }}>
                                            <CartesianGrid vertical={false} strokeDasharray="3 3" stroke="currentColor" strokeOpacity={0.08} />
                                            <XAxis dataKey="label" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                            <YAxis
                                                tick={{ fontSize: 11 }}
                                                tickLine={false}
                                                axisLine={false}
                                                tickFormatter={(v) => `${(v / 1000).toFixed(0)}k`}
                                            />
                                            <Tooltip formatter={(v: number) => [formatCurrency(v), 'Revenue']} contentStyle={{ fontSize: 12 }} />
                                            <Bar dataKey="value" fill="hsl(var(--primary))" radius={[3, 3, 0, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base">Billed vs Collected</CardTitle>
                                    <CardDescription>Last 6 months comparison</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ResponsiveContainer width="100%" height={200}>
                                        <BarChart data={bvcData} margin={{ left: 0, right: 8 }}>
                                            <CartesianGrid vertical={false} strokeDasharray="3 3" stroke="currentColor" strokeOpacity={0.08} />
                                            <XAxis dataKey="label" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                            <YAxis
                                                tick={{ fontSize: 11 }}
                                                tickLine={false}
                                                axisLine={false}
                                                tickFormatter={(v) => `${(v / 1000).toFixed(0)}k`}
                                            />
                                            <Tooltip formatter={(v: number) => formatCurrency(v)} contentStyle={{ fontSize: 12 }} />
                                            <Legend wrapperStyle={{ fontSize: 12 }} />
                                            <Bar dataKey="billed" name="Billed" fill="hsl(220 70% 60%)" radius={[3, 3, 0, 0]} />
                                            <Bar dataKey="collected" name="Collected" fill="hsl(142 71% 45%)" radius={[3, 3, 0, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>
                        </>
                    )}
                </div>

                {/* Bottom Row */}
                <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    {can.viewReports && (
                        <>
                            <Card className="xl:col-span-2">
                                <CardHeader className="pb-2">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Activity size={16} /> Recent Activity
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-0">
                                    {recentActivity.length === 0 ? (
                                        <p className="text-muted-foreground px-6 py-8 text-center text-sm">No recent activity</p>
                                    ) : (
                                        <div className="divide-y">
                                            {recentActivity.map((item, i) => (
                                                <Link
                                                    key={i}
                                                    href={item.link}
                                                    className="hover:bg-muted/50 group flex items-center justify-between px-6 py-3 transition-colors"
                                                >
                                                    <div className="flex min-w-0 items-center gap-3">
                                                        <div
                                                            className={`shrink-0 rounded-md p-1.5 ${item.type === 'bill' ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-green-50 dark:bg-green-900/20'}`}
                                                        >
                                                            {item.type === 'bill' ? (
                                                                <FileText size={13} className="text-blue-600 dark:text-blue-400" />
                                                            ) : (
                                                                <CreditCard size={13} className="text-green-600 dark:text-green-400" />
                                                            )}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <p className="group-hover:text-primary truncate text-sm font-medium transition-colors">
                                                                {item.label}
                                                            </p>
                                                            <p className="text-muted-foreground text-xs">{item.date}</p>
                                                        </div>
                                                    </div>
                                                    <div className="ml-4 flex shrink-0 items-center gap-2">
                                                        <span className="text-sm font-semibold">{formatCurrency(item.amount)}</span>
                                                        <Badge className={`text-xs ${statusColors[item.status] ?? 'bg-muted text-muted-foreground'}`}>
                                                            {item.status.replace('_', ' ')}
                                                        </Badge>
                                                    </div>
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </>
                    )}
                    <div className="flex flex-col gap-4">
                        {can.viewReports && (
                            <>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-base">Account Status</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        {[
                                            { label: 'Active', count: accountSummary.active, color: 'bg-green-500' },
                                            { label: 'Suspended', count: accountSummary.suspended, color: 'bg-yellow-500' },
                                            { label: 'Inactive', count: accountSummary.inactive, color: 'bg-gray-400' },
                                        ].map((row) => {
                                            const total = accountSummary.active + accountSummary.suspended + accountSummary.inactive || 1;
                                            const pct = Math.round((row.count / total) * 100);
                                            return (
                                                <div key={row.label}>
                                                    <div className="mb-1 flex justify-between text-sm">
                                                        <span className="text-muted-foreground">{row.label}</span>
                                                        <span className="font-medium">
                                                            {row.count} <span className="text-muted-foreground text-xs">({pct}%)</span>
                                                        </span>
                                                    </div>
                                                    <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                                        <div
                                                            className={`h-full ${row.color} rounded-full transition-all duration-500`}
                                                            style={{ width: `${pct}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </CardContent>
                                </Card>
                            </>
                        )}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-2">
                                {[
                                    { label: 'New Reading', href: route('meter-readings.create') },
                                    ...(can.viewReports ? [{ label: 'View Reports', href: route('reports.index') }] : []),
                                    ...(can.createBills ? [{ label: 'New Bill', href: route('billings.create') }] : []),
                                    ...(can.createPayments ? [{ label: 'New Payment', href: route('payments.create') }] : []),
                                    ...(can.viewAccounts ? [{ label: 'All Accounts', href: route('accounts.index') }] : []),
                                ].map((action) => (
                                    <Link key={action.label} href={action.href}>
                                        <Button variant="outline" size="sm" className="h-9 w-full text-xs">
                                            {action.label}
                                        </Button>
                                    </Link>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
