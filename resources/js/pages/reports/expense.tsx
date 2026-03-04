import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { Progress } from '@/components/ui/progress';
import { formatCurrency } from '@/lib/utils';
import { TrendingDown, CheckCircle, Clock, XCircle } from 'lucide-react';

interface Summary { total_all: number; total_approved: number; total_pending: number; total_rejected: number; expense_count: number; }
interface CategoryRow { category: string; total: number; approved: number; pending: number; count: number; }
interface MonthRow { month: string; label: string; total: number; approved: number; count: number; }

interface ExpenseReportProps {
    summary: Summary;
    by_category: CategoryRow[];
    monthly: MonthRow[];
    year: number;
    start_date: string;
    end_date: string;
    years: number[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: route('reports.index') },
    { title: 'Expense Report', href: '#' },
];

export default function ExpenseReport() {
    const { summary, by_category, monthly, year, start_date, end_date, years } = usePage<SharedData & ExpenseReportProps>().props;

    const applyFilter = (params: Record<string,string>) =>
        router.get(route('reports.expenses'), { year, start: start_date, end: end_date, ...params }, { preserveState: true });

    const maxCategory = Math.max(...by_category.map((c) => c.total), 1);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Expense Report" />
            <div className="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">
                <div className="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2"><TrendingDown size={22} className="text-orange-600" />Expense Report</h1>
                        <p className="text-sm text-muted-foreground">{start_date} to {end_date}</p>
                    </div>
                    <div className="flex gap-2 items-center flex-wrap">
                        <select value={year} onChange={(e) => applyFilter({ year: e.target.value })}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm">
                            {years.map((y) => <option key={y} value={y}>{y}</option>)}
                        </select>
                        <Input type="date" value={start_date} onChange={(e) => applyFilter({ start: e.target.value })} className="w-38" />
                        <span className="text-sm text-muted-foreground">to</span>
                        <Input type="date" value={end_date} onChange={(e) => applyFilter({ end: e.target.value })} className="w-38" />
                    </div>
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    {[
                        { label: 'Total Expenses', value: formatCurrency(summary.total_all), icon: TrendingDown, color: 'text-gray-600', bg: 'bg-gray-50 dark:bg-gray-900/30' },
                        { label: 'Approved', value: formatCurrency(summary.total_approved), icon: CheckCircle, color: 'text-green-600', bg: 'bg-green-50 dark:bg-green-950/30' },
                        { label: 'Pending', value: formatCurrency(summary.total_pending), icon: Clock, color: 'text-yellow-600', bg: 'bg-yellow-50 dark:bg-yellow-950/30' },
                        { label: 'Rejected', value: formatCurrency(summary.total_rejected), icon: XCircle, color: 'text-red-600', bg: 'bg-red-50 dark:bg-red-950/30' },
                    ].map((c) => (
                        <Card key={c.label}>
                            <CardContent className="pt-5 flex items-center gap-3">
                                <div className={`w-9 h-9 rounded-lg ${c.bg} flex items-center justify-center shrink-0`}>
                                    <c.icon size={18} className={c.color} />
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">{c.label}</p>
                                    <p className="font-semibold">{c.value}</p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    {/* By category */}
                    <Card>
                        <CardHeader><CardTitle>By Category</CardTitle></CardHeader>
                        <CardContent className="space-y-3">
                            {by_category.length === 0 ? <p className="text-sm text-muted-foreground">No data.</p> :
                                by_category.map((row) => (
                                    <div key={row.category}>
                                        <div className="flex justify-between text-sm mb-1">
                                            <span className="font-medium">{row.category}</span>
                                            <span>{formatCurrency(row.total)} <span className="text-muted-foreground text-xs">({row.count})</span></span>
                                        </div>
                                        <Progress value={(row.total / maxCategory) * 100} className="h-2" />
                                        <div className="flex gap-3 text-xs text-muted-foreground mt-1">
                                            <span className="text-green-600">✓ {formatCurrency(row.approved)}</span>
                                            <span className="text-yellow-600">~ {formatCurrency(row.pending)}</span>
                                        </div>
                                    </div>
                                ))}
                        </CardContent>
                    </Card>

                    {/* Monthly breakdown */}
                    <Card>
                        <CardHeader><CardTitle>Monthly Breakdown</CardTitle></CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Month</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                        <TableHead className="text-right">Approved</TableHead>
                                        <TableHead className="text-right"># Items</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {monthly.length === 0 ? (
                                        <TableRow><TableCell colSpan={4} className="text-center text-muted-foreground">No data</TableCell></TableRow>
                                    ) : monthly.map((row) => (
                                        <TableRow key={row.month}>
                                            <TableCell className="text-sm">{row.label}</TableCell>
                                            <TableCell className="text-right text-sm font-medium">{formatCurrency(row.total)}</TableCell>
                                            <TableCell className="text-right text-sm text-green-600">{formatCurrency(row.approved)}</TableCell>
                                            <TableCell className="text-right text-sm">{row.count}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}