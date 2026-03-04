import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { formatCurrency } from '@/lib/utils';
import { TrendingUp } from 'lucide-react';

interface Summary { total_revenue: number; cash_collected: number; total_expenses: number; gross_profit: number; profit_margin: number; outstanding_revenue: number; }
interface MonthRow { month: string; label: string; revenue: number; expenses: number; cash_in: number; net_profit: number; }

interface PLProps { summary: Summary; monthly: MonthRow[]; year: number; start_date: string; end_date: string; years: number[]; }

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: route('reports.index') },
    { title: 'P&L Report', href: '#' },
];

export default function PLReport() {
    const { summary, monthly, year, start_date, end_date, years } = usePage<SharedData & PLProps>().props;

    const applyFilter = (params: Record<string, string>) =>
        router.get(route('reports.pl'), { year, start: start_date, end: end_date, ...params }, { preserveState: true });

    const profitColor = summary.gross_profit >= 0 ? 'text-green-600' : 'text-red-600';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="P&L Report" />
            <div className="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">
                <div className="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2"><TrendingUp size={22} className="text-green-600" />Revenue vs Expenses</h1>
                        <p className="text-sm text-muted-foreground">{start_date} to {end_date}</p>
                    </div>
                    <div className="flex gap-2 items-center flex-wrap">
                        <select value={year} onChange={(e) => applyFilter({ year: e.target.value })}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm">
                            {years.map((y) => <option key={y} value={y}>{y}</option>)}
                        </select>
                        <Input type="date" value={start_date} onChange={(e) => applyFilter({ start: e.target.value })} className="w-38" />
                        <span className="text-sm">to</span>
                        <Input type="date" value={end_date} onChange={(e) => applyFilter({ end: e.target.value })} className="w-38" />
                    </div>
                </div>

                {/* KPI cards */}
                <div className="grid grid-cols-2 lg:grid-cols-3 gap-4">
                    {[
                        { label: 'Revenue Billed', value: formatCurrency(summary.total_revenue), sub: `${formatCurrency(summary.cash_collected)} cash collected`, color: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-950/30' },
                        { label: 'Approved Expenses', value: formatCurrency(summary.total_expenses), sub: 'Only approved expenses counted', color: 'text-orange-600', bg: 'bg-orange-50 dark:bg-orange-950/30' },
                        { label: 'Net Surplus / Deficit', value: formatCurrency(summary.gross_profit), sub: `${summary.profit_margin}% margin`, color: profitColor, bg: summary.gross_profit >= 0 ? 'bg-green-50 dark:bg-green-950/30' : 'bg-red-50 dark:bg-red-950/30' },
                        { label: 'Outstanding Revenue', value: formatCurrency(summary.outstanding_revenue), sub: 'Billed but not yet collected', color: 'text-yellow-600', bg: 'bg-yellow-50 dark:bg-yellow-950/30' },
                    ].map((c) => (
                        <Card key={c.label}>
                            <CardContent className="pt-5">
                                <p className="text-xs text-muted-foreground">{c.label}</p>
                                <p className={`text-xl font-bold mt-1 ${c.color}`}>{c.value}</p>
                                <p className="text-xs text-muted-foreground mt-1">{c.sub}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Monthly table */}
                <Card>
                    <CardHeader><CardTitle>Monthly Breakdown</CardTitle></CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Month</TableHead>
                                    <TableHead className="text-right">Revenue Billed</TableHead>
                                    <TableHead className="text-right">Cash Received</TableHead>
                                    <TableHead className="text-right">Expenses</TableHead>
                                    <TableHead className="text-right">Net Profit</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {monthly.length === 0 ? (
                                    <TableRow><TableCell colSpan={5} className="text-center text-muted-foreground py-8">No data for this period.</TableCell></TableRow>
                                ) : (
                                    <>
                                        {monthly.map((row) => (
                                            <TableRow key={row.month}>
                                                <TableCell className="text-sm font-medium">{row.label}</TableCell>
                                                <TableCell className="text-right text-sm">{formatCurrency(row.revenue)}</TableCell>
                                                <TableCell className="text-right text-sm text-blue-600">{formatCurrency(row.cash_in)}</TableCell>
                                                <TableCell className="text-right text-sm text-orange-600">{formatCurrency(row.expenses)}</TableCell>
                                                <TableCell className={`text-right text-sm font-semibold ${row.net_profit >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                    {formatCurrency(row.net_profit)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        <TableRow className="border-t-2 font-bold bg-muted/30">
                                            <TableCell>Total</TableCell>
                                            <TableCell className="text-right">{formatCurrency(summary.total_revenue)}</TableCell>
                                            <TableCell className="text-right text-blue-600">{formatCurrency(summary.cash_collected)}</TableCell>
                                            <TableCell className="text-right text-orange-600">{formatCurrency(summary.total_expenses)}</TableCell>
                                            <TableCell className={`text-right ${profitColor}`}>{formatCurrency(summary.gross_profit)}</TableCell>
                                        </TableRow>
                                    </>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}