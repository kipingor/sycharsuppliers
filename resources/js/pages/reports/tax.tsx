import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table, TableBody, TableCell, TableHead,
    TableHeader, TableRow,
} from '@/components/table';
import { Download, TrendingUp, TrendingDown, Banknote, Receipt, AlertCircle, BarChart3 } from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import { useState } from 'react';

// ─── Types ───────────────────────────────────────────────────────────────────

interface MonthlyRow {
    month: string;
    label: string;
    billed: number;
    bill_count: number;
    collected: number;
    payment_count: number;
    variance: number;
}

interface MethodRow  { method: string; total: number; count: number }
interface StatusRow  { status: string; total: number; count: number }

interface TaxSummary {
    total_billed: number;
    total_bill_count: number;
    total_collected: number;
    total_payment_count: number;
    outstanding_balance: number;
    collection_rate: number;
    monthly: MonthlyRow[];
    by_method: MethodRow[];
    by_status: StatusRow[];
}

interface TaxPageProps {
    summary: TaxSummary;
    year: number;
    start_date: string;
    end_date: string;
    years: number[];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const fmt = (n: number) =>
    new Intl.NumberFormat('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);

const pct = (n: number) => `${n.toFixed(1)}%`;

const STATUS_COLORS: Record<string, string> = {
    paid:           'bg-lime-400/20  text-lime-700  dark:text-lime-300',
    pending:        'bg-sky-400/15   text-sky-700   dark:text-sky-400',
    overdue:        'bg-pink-400/15  text-pink-700  dark:text-pink-400',
    partially_paid: 'bg-blue-400/15  text-blue-700  dark:text-blue-400',
    voided:         'bg-gray-400/15  text-gray-600  dark:text-gray-400',
};

// ─── KPI Card ────────────────────────────────────────────────────────────────

function KpiCard({
    label, value, sub, icon: Icon, accent,
}: {
    label: string;
    value: string;
    sub?: string;
    icon: React.ElementType;
    accent: string;
}) {
    return (
        <Card className="relative overflow-hidden">
            <CardContent className="pt-5 pb-4">
                <div className="flex items-start justify-between">
                    <div>
                        <p className="text-xs text-muted-foreground uppercase tracking-wide">{label}</p>
                        <p className={`text-2xl font-bold mt-1 ${accent}`}>{value}</p>
                        {sub && <p className="text-xs text-muted-foreground mt-1">{sub}</p>}
                    </div>
                    <div className={`rounded-full p-2 ${accent.replace('text-', 'bg-').replace('-700', '-100').replace('-300', '-900/20')}`}>
                        <Icon size={18} className={accent} />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

// ─── Mini bar chart (pure CSS) ────────────────────────────────────────────────

function MiniBar({ value, max, color }: { value: number; max: number; color: string }) {
    const width = max > 0 ? Math.max(2, (value / max) * 100) : 0;
    return (
        <div className="w-full bg-muted rounded-full h-1.5 mt-1">
            <div className={`h-1.5 rounded-full ${color}`} style={{ width: `${width}%` }} />
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function TaxReport() {
    const { summary, year, start_date, end_date, years } =
        usePage<SharedData & TaxPageProps>().props;

    const [selectedYear, setSelectedYear] = useState(year.toString());
    const [customStart, setCustomStart]   = useState(start_date);
    const [customEnd,   setCustomEnd]     = useState(end_date);
    const [useCustom,   setUseCustom]     = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Reports', href: route('reports.index') },
        { title: 'Tax Report', href: '#' },
    ];

    const applyFilters = () => {
        if (useCustom) {
            router.get(route('reports.tax'), { start: customStart, end: customEnd });
        } else {
            router.get(route('reports.tax'), { year: selectedYear });
        }
    };

    const downloadPdf = () => {
        const params = useCustom
            ? new URLSearchParams({ start: customStart, end: customEnd })
            : new URLSearchParams({ year: selectedYear });
        window.location.href = `${route('reports.tax.download')}?${params.toString()}`;
    };

    const maxBilled    = Math.max(...summary.monthly.map(m => m.billed),    1);
    const maxCollected = Math.max(...summary.monthly.map(m => m.collected), 1);

    const collectionRateColor =
        summary.collection_rate >= 80 ? 'text-lime-600 dark:text-lime-400'
        : summary.collection_rate >= 60 ? 'text-amber-600 dark:text-amber-400'
        : 'text-rose-600 dark:text-rose-400';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tax Report ${year}`} />

            <div className="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">

                {/* ── Page header ─────────────────────────────────────── */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2">
                            <BarChart3 size={22} className="text-blue-600" />
                            Tax Report
                        </h1>
                        <p className="text-sm text-muted-foreground mt-0.5">
                            Income tax statement · {start_date} → {end_date}
                        </p>
                    </div>
                    <Button onClick={downloadPdf} className="gap-2 shrink-0">
                        <Download size={16} /> Download PDF
                    </Button>
                </div>

                {/* ── Filters ─────────────────────────────────────────── */}
                <Card>
                    <CardContent className="pt-4 pb-4">
                        <div className="flex flex-wrap gap-4 items-end">
                            <label className="flex items-center gap-2 text-sm cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={useCustom}
                                    onChange={e => setUseCustom(e.target.checked)}
                                    className="rounded"
                                />
                                Custom date range
                            </label>

                            {!useCustom ? (
                                <div className="flex flex-col gap-1">
                                    <span className="text-xs text-muted-foreground">Tax year</span>
                                    <select
                                        value={selectedYear}
                                        onChange={e => setSelectedYear(e.target.value)}
                                        className="border rounded px-3 py-1.5 text-sm bg-background"
                                    >
                                        {years.map(y => (
                                            <option key={y} value={y}>{y}</option>
                                        ))}
                                    </select>
                                </div>
                            ) : (
                                <>
                                    <div className="flex flex-col gap-1">
                                        <span className="text-xs text-muted-foreground">From</span>
                                        <input
                                            type="date"
                                            value={customStart}
                                            onChange={e => setCustomStart(e.target.value)}
                                            className="border rounded px-3 py-1.5 text-sm bg-background"
                                        />
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <span className="text-xs text-muted-foreground">To</span>
                                        <input
                                            type="date"
                                            value={customEnd}
                                            onChange={e => setCustomEnd(e.target.value)}
                                            className="border rounded px-3 py-1.5 text-sm bg-background"
                                        />
                                    </div>
                                </>
                            )}

                            <Button onClick={applyFilters} variant="outline" size="sm">
                                Apply
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* ── KPI cards ───────────────────────────────────────── */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <KpiCard
                        label="Gross Revenue Billed"
                        value={formatCurrency(summary.total_billed)}
                        sub={`${summary.total_bill_count.toLocaleString()} bills issued`}
                        icon={Receipt}
                        accent="text-blue-700 dark:text-blue-400"
                    />
                    <KpiCard
                        label="Cash Collected"
                        value={formatCurrency(summary.total_collected)}
                        sub={`${summary.total_payment_count.toLocaleString()} payments`}
                        icon={Banknote}
                        accent="text-lime-700 dark:text-lime-400"
                    />
                    <KpiCard
                        label="Outstanding Receivables"
                        value={formatCurrency(summary.outstanding_balance)}
                        sub="Unpaid as at period end"
                        icon={AlertCircle}
                        accent="text-amber-700 dark:text-amber-400"
                    />
                    <KpiCard
                        label="Collection Rate"
                        value={pct(summary.collection_rate)}
                        sub="Collected ÷ Billed"
                        icon={summary.collection_rate >= 80 ? TrendingUp : TrendingDown}
                        accent={collectionRateColor}
                    />
                </div>

                {/* ── Monthly breakdown ───────────────────────────────── */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Monthly Revenue Breakdown</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {summary.monthly.length === 0 ? (
                            <p className="text-sm text-muted-foreground text-center py-8">
                                No data for this period.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Month</TableHead>
                                        <TableHead className="text-right">Bills</TableHead>
                                        <TableHead>
                                            <div>Amount Billed</div>
                                        </TableHead>
                                        <TableHead className="text-right">Payments</TableHead>
                                        <TableHead>
                                            <div>Amount Collected</div>
                                        </TableHead>
                                        <TableHead className="text-right">Variance</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {summary.monthly.map(row => (
                                        <TableRow key={row.month}>
                                            <TableCell className="font-medium">{row.label}</TableCell>
                                            <TableCell className="text-right text-muted-foreground text-sm">
                                                {row.bill_count.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="min-w-[140px]">
                                                <span className="font-medium">{fmt(row.billed)}</span>
                                                <MiniBar value={row.billed} max={maxBilled} color="bg-blue-500" />
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground text-sm">
                                                {row.payment_count.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="min-w-[140px]">
                                                <span className="font-medium text-lime-700 dark:text-lime-400">
                                                    {fmt(row.collected)}
                                                </span>
                                                <MiniBar value={row.collected} max={maxCollected} color="bg-lime-500" />
                                            </TableCell>
                                            <TableCell className={`text-right font-medium text-sm ${row.variance > 0 ? 'text-rose-600' : 'text-lime-600'}`}>
                                                {row.variance > 0 ? '+' : ''}{fmt(row.variance)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {/* Totals row */}
                                    <TableRow className="border-t-2 font-bold bg-muted/40">
                                        <TableCell>TOTAL</TableCell>
                                        <TableCell className="text-right">
                                            {summary.total_bill_count.toLocaleString()}
                                        </TableCell>
                                        <TableCell>{fmt(summary.total_billed)}</TableCell>
                                        <TableCell className="text-right">
                                            {summary.total_payment_count.toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-lime-700 dark:text-lime-400">
                                            {fmt(summary.total_collected)}
                                        </TableCell>
                                        <TableCell className={`text-right ${(summary.total_billed - summary.total_collected) > 0 ? 'text-rose-600' : 'text-lime-600'}`}>
                                            {fmt(summary.total_billed - summary.total_collected)}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* ── Bottom row: payment methods + bill status ────────── */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Collections by Payment Method</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {summary.by_method.length === 0 ? (
                                <p className="text-sm text-muted-foreground text-center py-6">No payments.</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Method</TableHead>
                                            <TableHead className="text-right">Transactions</TableHead>
                                            <TableHead className="text-right">Amount</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {summary.by_method.map(m => (
                                            <TableRow key={m.method}>
                                                <TableCell className="font-medium capitalize">{m.method}</TableCell>
                                                <TableCell className="text-right text-muted-foreground">
                                                    {m.count.toLocaleString()}
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {fmt(m.total)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Bills by Status</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {summary.by_status.length === 0 ? (
                                <p className="text-sm text-muted-foreground text-center py-6">No bills.</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Count</TableHead>
                                            <TableHead className="text-right">Amount</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {summary.by_status.map(s => (
                                            <TableRow key={s.status}>
                                                <TableCell>
                                                    <Badge className={STATUS_COLORS[s.status] ?? 'bg-gray-100 text-gray-600'}>
                                                        {s.status.replace('_', ' ')}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right text-muted-foreground">
                                                    {s.count.toLocaleString()}
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {fmt(s.total)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* ── Tax filing note ──────────────────────────────────── */}
                <Card className="border-amber-200 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-900">
                    <CardContent className="pt-4 pb-4">
                        <div className="flex gap-3">
                            <AlertCircle size={18} className="text-amber-600 shrink-0 mt-0.5" />
                            <div className="text-sm text-amber-800 dark:text-amber-300 space-y-1">
                                <p className="font-semibold">For Income Tax Filing</p>
                                <p>
                                    The <strong>Gross Revenue Billed</strong> figure represents total revenue recognised in the period.
                                    The <strong>Cash Collected</strong> figure represents actual receipts — use this if you report on a
                                    cash basis. Outstanding receivables may be deductible as bad debts subject to KRA provisions.
                                    Consult your tax advisor regarding allowable deductions (operating expenses, depreciation, etc.)
                                    before filing your income tax return.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

            </div>
        </AppLayout>
    );
}