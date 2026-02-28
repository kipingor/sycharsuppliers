import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { Badge } from '@/components/ui/badge';
import { Download, Mail, FileText, TrendingUp, TrendingDown, Banknote, Receipt } from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import { useState } from 'react';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Transaction {
    date: string;
    type: 'bill' | 'payment' | 'credit_note';
    reference: string;
    description: string;
    debit: number | null;
    credit: number | null;
    running_balance: number;
    bill_id?: number;
    status?: string;
}

interface Statement {
    account: { name: string; number: string; address?: string; phone?: string; email?: string };
    company: { name: string };
    period: { start: string; end: string };
    opening_balance: number;
    total_billed: number;
    total_paid: number;
    total_credited: number;
    closing_balance: number;
    transactions: Transaction[];
}

interface Account {
    id: number;
    name: string;
    account_number?: string;
    email?: string;
}

interface Props {
    account: Account;
    statement: Statement;
    start_date: string;
    end_date: string;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const fmt = (n: number) =>
    new Intl.NumberFormat('en-KE', { minimumFractionDigits: 2 }).format(n);

const TYPE_CONFIG = {
    bill:        { label: 'Bill',        class: 'bg-blue-400/15 text-blue-700 dark:text-blue-400' },
    payment:     { label: 'Payment',     class: 'bg-lime-400/15 text-lime-700 dark:text-lime-400' },
    credit_note: { label: 'Credit Note', class: 'bg-amber-400/15 text-amber-700 dark:text-amber-400' },
};

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function AccountStatementPage() {
    const { account, statement, start_date, end_date } = usePage<SharedData & Props>().props;

    const [start,      setStart]      = useState(start_date);
    const [end,        setEnd]        = useState(end_date);
    const [emailAddr,  setEmailAddr]  = useState(account.email ?? '');
    const [showEmail,  setShowEmail]  = useState(false);
    const [sending,    setSending]    = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Accounts', href: route('accounts.index') },
        { title: account.name, href: route('accounts.show', account.id) },
        { title: 'Statement', href: '#' },
    ];

    const applyDates = () => {
        router.get(route('accounts.statement', account.id), { start, end }, { preserveState: true });
    };

    const downloadPdf = () => {
        window.location.href = `${route('accounts.statement.download', account.id)}?start=${start}&end=${end}`;
    };

    const sendEmail = () => {
        if (!emailAddr) return;
        setSending(true);
        router.post(
            route('accounts.statement.send', account.id),
            { email: emailAddr, start, end },
            { onFinish: () => { setSending(false); setShowEmail(false); } },
        );
    };

    const openingRunning = statement.opening_balance;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Account Statement — ${account.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

                {/* Page header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2">
                            <FileText size={22} className="text-blue-600" />
                            Account Statement
                        </h1>
                        <p className="text-sm text-muted-foreground mt-0.5">
                            {account.name} · {account.account_number}
                        </p>
                    </div>
                    <div className="flex gap-2 flex-wrap">
                        <Button variant="outline" size="sm" onClick={() => setShowEmail(v => !v)} className="gap-2">
                            <Mail size={15} /> Email
                        </Button>
                        <Button size="sm" onClick={downloadPdf} className="gap-2">
                            <Download size={15} /> Download PDF
                        </Button>
                    </div>
                </div>

                {/* Email panel */}
                {showEmail && (
                    <Card className="border-blue-200 bg-blue-50 dark:bg-blue-950/20 dark:border-blue-900">
                        <CardContent className="pt-4 pb-4">
                            <div className="flex flex-wrap gap-3 items-end">
                                <div className="flex-1 min-w-[220px]">
                                    <label className="text-xs font-medium text-muted-foreground block mb-1">Email address</label>
                                    <input
                                        type="email"
                                        value={emailAddr}
                                        onChange={e => setEmailAddr(e.target.value)}
                                        placeholder="resident@example.com"
                                        className="w-full border rounded-md px-3 py-2 text-sm bg-background"
                                    />
                                </div>
                                <Button size="sm" onClick={sendEmail} disabled={sending || !emailAddr}>
                                    {sending ? 'Sending…' : 'Send Statement'}
                                </Button>
                                <Button variant="ghost" size="sm" onClick={() => setShowEmail(false)}>Cancel</Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Date range filter */}
                <Card>
                    <CardContent className="pt-4 pb-4">
                        <div className="flex flex-wrap gap-3 items-end">
                            <div>
                                <label className="text-xs text-muted-foreground block mb-1">From</label>
                                <input
                                    type="date"
                                    value={start}
                                    onChange={e => setStart(e.target.value)}
                                    className="border rounded-md px-3 py-2 text-sm bg-background"
                                />
                            </div>
                            <div>
                                <label className="text-xs text-muted-foreground block mb-1">To</label>
                                <input
                                    type="date"
                                    value={end}
                                    onChange={e => setEnd(e.target.value)}
                                    className="border rounded-md px-3 py-2 text-sm bg-background"
                                />
                            </div>
                            <Button variant="outline" size="sm" onClick={applyDates}>Apply</Button>
                        </div>
                    </CardContent>
                </Card>

                {/* KPI cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="pt-5 pb-4">
                            <p className="text-xs text-muted-foreground uppercase tracking-wide">Opening Balance</p>
                            <p className="text-xl font-bold mt-1">{formatCurrency(statement.opening_balance)}</p>
                            <p className="text-xs text-muted-foreground mt-1">Before {statement.period.start}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-5 pb-4">
                            <p className="text-xs text-muted-foreground uppercase tracking-wide">Total Charged</p>
                            <p className="text-xl font-bold text-blue-600 dark:text-blue-400 mt-1">{formatCurrency(statement.total_billed)}</p>
                            <p className="text-xs text-muted-foreground mt-1">Bills in period</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-5 pb-4">
                            <p className="text-xs text-muted-foreground uppercase tracking-wide">Total Paid</p>
                            <p className="text-xl font-bold text-lime-600 dark:text-lime-400 mt-1">{formatCurrency(statement.total_paid)}</p>
                            <p className="text-xs text-muted-foreground mt-1">
                                {statement.total_credited > 0 && `+ ${formatCurrency(statement.total_credited)} credited`}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className={statement.closing_balance > 0 ? 'border-rose-200 dark:border-rose-900' : 'border-lime-200 dark:border-lime-900'}>
                        <CardContent className="pt-5 pb-4">
                            <p className="text-xs text-muted-foreground uppercase tracking-wide">Closing Balance</p>
                            <p className={`text-xl font-bold mt-1 ${statement.closing_balance > 0 ? 'text-rose-600' : 'text-lime-600 dark:text-lime-400'}`}>
                                {formatCurrency(statement.closing_balance)}
                            </p>
                            <p className="text-xs text-muted-foreground mt-1">
                                {statement.closing_balance <= 0 ? '✓ Fully settled' : 'Amount outstanding'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Transaction ledger */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Transaction Ledger</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead className="text-right">Debit</TableHead>
                                    <TableHead className="text-right">Credit</TableHead>
                                    <TableHead className="text-right">Balance</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {/* Opening row */}
                                <TableRow className="bg-muted/50 font-medium">
                                    <TableCell>{statement.period.start}</TableCell>
                                    <TableCell colSpan={3} className="text-muted-foreground italic text-sm">
                                        Opening balance brought forward
                                    </TableCell>
                                    <TableCell className="text-right">—</TableCell>
                                    <TableCell className="text-right">—</TableCell>
                                    <TableCell className="text-right font-mono">
                                        {fmt(statement.opening_balance)}
                                    </TableCell>
                                </TableRow>

                                {statement.transactions.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-center text-muted-foreground py-10">
                                            No transactions in this period.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    statement.transactions.map((t, i) => {
                                        const runningTotal = openingRunning + t.running_balance;
                                        return (
                                            <TableRow key={i}>
                                                <TableCell className="font-mono text-sm whitespace-nowrap">{t.date}</TableCell>
                                                <TableCell className="font-mono text-sm">{t.reference}</TableCell>
                                                <TableCell>
                                                    <Badge className={TYPE_CONFIG[t.type]?.class ?? ''}>
                                                        {TYPE_CONFIG[t.type]?.label ?? t.type}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-sm max-w-[200px]">
                                                    <span className="truncate block" title={t.description}>
                                                        {t.description}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-sm text-rose-600">
                                                    {t.debit != null ? fmt(t.debit) : '—'}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-sm text-lime-600 dark:text-lime-400">
                                                    {t.credit != null ? fmt(t.credit) : '—'}
                                                </TableCell>
                                                <TableCell className={`text-right font-mono text-sm font-medium ${runningTotal > 0 ? 'text-rose-600' : 'text-lime-600 dark:text-lime-400'}`}>
                                                    {fmt(runningTotal)}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}

                                {/* Closing row */}
                                <TableRow className="border-t-2 font-bold bg-muted/40">
                                    <TableCell>{statement.period.end}</TableCell>
                                    <TableCell colSpan={3} className="font-bold">Closing Balance</TableCell>
                                    <TableCell className="text-right font-mono">{fmt(statement.total_billed)}</TableCell>
                                    <TableCell className="text-right font-mono">{fmt(statement.total_paid + statement.total_credited)}</TableCell>
                                    <TableCell className={`text-right font-mono font-bold ${statement.closing_balance > 0 ? 'text-rose-600' : 'text-lime-600 dark:text-lime-400'}`}>
                                        {fmt(statement.closing_balance)}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}