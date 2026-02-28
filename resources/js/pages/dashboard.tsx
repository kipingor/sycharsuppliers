import AppLayout from '@/layouts/app-layout';
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type User } from '@/types';
import { Download, TrendingUp, Users, Gauge, AlertCircle, FileText, CreditCard, Activity } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { formatCurrency } from '@/lib/utils';

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
    can: { downloadReadingList: boolean };
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
    const { user, metrics, chartData, recentActivity, accountSummary, period, can } =
        usePage<SharedData & DashboardProps>().props;

    const greeting = (() => {
        const h = new Date().getHours();
        if (h < 12) return 'Good morning';
        if (h < 17) return 'Good afternoon';
        if (h < 20) return 'Good evening';
        return 'Good night';
    })();

    const changePeriod = (value: string) => {
        router.get(route('dashboard'), { period: value }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
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
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto">

                {/* Header */}
                <div className="flex items-end justify-between flex-wrap gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-zinc-900 dark:text-white">
                            {greeting}, {user.name.split(' ')[0]} 👋
                        </h1>
                        <p className="text-sm text-muted-foreground mt-0.5">
                            Here's what's happening with Sychar Water Billing.
                        </p>
                    </div>
                    <div className="flex gap-2 flex-wrap">
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
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    {metricCards.map(card => (
                        <Card key={card.title} className={`border-2 ${card.color}`}>
                            <CardContent className="pt-5">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-xs text-muted-foreground uppercase tracking-wide font-medium">{card.title}</p>
                                        <p className="text-2xl font-bold mt-1">{card.value}</p>
                                        <p className="text-xs text-muted-foreground mt-1">{card.sub}</p>
                                    </div>
                                    <div className="mt-0.5">{card.icon}</div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
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
                                    <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} tickFormatter={v => `${(v / 1000).toFixed(0)}k`} />
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
                                    <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} tickFormatter={v => `${(v / 1000).toFixed(0)}k`} />
                                    <Tooltip formatter={(v: number) => formatCurrency(v)} contentStyle={{ fontSize: 12 }} />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Bar dataKey="billed" name="Billed" fill="hsl(220 70% 60%)" radius={[3, 3, 0, 0]} />
                                    <Bar dataKey="collected" name="Collected" fill="hsl(142 71% 45%)" radius={[3, 3, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                </div>

                {/* Bottom Row */}
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">

                    <Card className="xl:col-span-2">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base flex items-center gap-2">
                                <Activity size={16} /> Recent Activity
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {recentActivity.length === 0 ? (
                                <p className="text-sm text-muted-foreground px-6 py-8 text-center">No recent activity</p>
                            ) : (
                                <div className="divide-y">
                                    {recentActivity.map((item, i) => (
                                        <Link key={i} href={item.link} className="flex items-center justify-between px-6 py-3 hover:bg-muted/50 transition-colors group">
                                            <div className="flex items-center gap-3 min-w-0">
                                                <div className={`p-1.5 rounded-md shrink-0 ${item.type === 'bill' ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-green-50 dark:bg-green-900/20'}`}>
                                                    {item.type === 'bill'
                                                        ? <FileText size={13} className="text-blue-600 dark:text-blue-400" />
                                                        : <CreditCard size={13} className="text-green-600 dark:text-green-400" />
                                                    }
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-sm font-medium truncate group-hover:text-primary transition-colors">{item.label}</p>
                                                    <p className="text-xs text-muted-foreground">{item.date}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2 shrink-0 ml-4">
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

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Account Status</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {[
                                    { label: 'Active', count: accountSummary.active, color: 'bg-green-500' },
                                    { label: 'Suspended', count: accountSummary.suspended, color: 'bg-yellow-500' },
                                    { label: 'Inactive', count: accountSummary.inactive, color: 'bg-gray-400' },
                                ].map(row => {
                                    const total = (accountSummary.active + accountSummary.suspended + accountSummary.inactive) || 1;
                                    const pct = Math.round((row.count / total) * 100);
                                    return (
                                        <div key={row.label}>
                                            <div className="flex justify-between text-sm mb-1">
                                                <span className="text-muted-foreground">{row.label}</span>
                                                <span className="font-medium">{row.count} <span className="text-xs text-muted-foreground">({pct}%)</span></span>
                                            </div>
                                            <div className="h-1.5 bg-muted rounded-full overflow-hidden">
                                                <div className={`h-full ${row.color} rounded-full transition-all duration-500`} style={{ width: `${pct}%` }} />
                                            </div>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-2">
                                {[
                                    { label: 'New Reading', href: route('meter-readings.create') },
                                    { label: 'New Bill', href: route('billings.create') },
                                    { label: 'New Payment', href: route('payments.create') },
                                    { label: 'All Accounts', href: route('accounts.index') },
                                ].map(action => (
                                    <Link key={action.label} href={action.href}>
                                        <Button variant="outline" size="sm" className="w-full text-xs h-9">
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