import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { formatCurrency } from '@/lib/utils';
import { AlertTriangle, FileMinus } from 'lucide-react';
import { useMemo, useState } from 'react';

interface AccountOption {
    id: number;
    name: string;
    account_number: string;
    status: string;
}

interface Bill {
    id: number;
    billing_period: string;
    total_amount: number;
    stored_amount_due: number;
    balance: number;
    status: string;
    account: AccountOption;
}

interface PageData {
    data: Bill[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    bills: PageData;
    accounts: AccountOption[];
    filters: { through_year: number; account_id: number | '' };
    errors?: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Credit Notes', href: route('credit-notes.index') },
    { title: 'Apply Bad Debt Write-off', href: '#' },
];

export default function ApplyCreditNotes() {
    const { bills, accounts, filters, errors = {} } = usePage<SharedData & Props>().props;
    const [throughYear, setThroughYear] = useState(String(filters.through_year));
    const [accountId, setAccountId] = useState(String(filters.account_id ?? ''));
    const [selected, setSelected] = useState<number[]>([]);
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const selectedTotal = useMemo(
        () => bills.data.filter((bill) => selected.includes(bill.id)).reduce((sum, bill) => sum + bill.balance, 0),
        [bills.data, selected],
    );

    const filterBills = () => {
        setSelected([]);
        router.get(route('credit-notes.apply'), {
            through_year: throughYear,
            account_id: accountId || undefined,
        }, { preserveState: true, replace: true });
    };

    const toggleAll = (checked: boolean) => {
        setSelected(checked ? bills.data.map((bill) => bill.id) : []);
    };

    const applyWriteOff = () => {
        if (selected.length === 0 || reason.trim().length < 10) return;
        if (!window.confirm(`Apply bad-debt credit notes to ${selected.length} selected bill(s), totaling ${formatCurrency(selectedTotal)}?`)) return;

        setSubmitting(true);
        router.post(route('credit-notes.apply-bulk'), {
            billing_ids: selected,
            reason: reason.trim(),
        }, {
            onError: () => setSubmitting(false),
            onSuccess: () => {
                setSelected([]);
                setReason('');
                setSubmitting(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Apply Bad Debt Write-off" />
            <div className="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2">
                            <FileMinus size={22} className="text-amber-600" />
                            Apply Bad Debt Write-off
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Select old unpaid bills. One full-balance credit note will be recorded for each selected bill.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={route('credit-notes.index')}>View credit notes</Link>
                    </Button>
                </div>

                <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:bg-amber-950/20 dark:text-amber-200">
                    <div className="flex gap-2">
                        <AlertTriangle size={18} className="shrink-0" />
                        <p>Write-offs are accounting adjustments, not payments. Only bills with a consistent open balance through the previous year are eligible. Review source records and approval before applying; submitted write-offs are individually auditable.</p>
                    </div>
                </div>

                <Card>
                    <CardContent className="pt-4">
                        <div className="flex items-end gap-3 flex-wrap">
                            <label className="flex flex-col gap-1 text-sm">
                                Through billing year
                                <input
                                    type="number"
                                    min="2000"
                                    max={new Date().getFullYear()}
                                    value={throughYear}
                                    onChange={(event) => setThroughYear(event.target.value)}
                                    className="h-9 w-36 rounded-md border bg-background px-3"
                                />
                            </label>
                            <label className="flex flex-col gap-1 text-sm min-w-64">
                                Account (optional)
                                <select
                                    value={accountId}
                                    onChange={(event) => setAccountId(event.target.value)}
                                    className="h-9 rounded-md border bg-background px-3"
                                >
                                    <option value="">All accounts</option>
                                    {accounts.map((account) => (
                                        <option key={account.id} value={account.id}>
                                            {account.account_number} · {account.name} ({account.status})
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Button onClick={filterBills}>Find eligible bills</Button>
                            <span className="text-sm text-muted-foreground">{bills.total} bill(s) found</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-4">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>
                                            <input
                                                aria-label="Select all visible bills"
                                                type="checkbox"
                                                checked={bills.data.length > 0 && selected.length === bills.data.length}
                                                onChange={(event) => toggleAll(event.target.checked)}
                                            />
                                        </TableHead>
                                        <TableHead>Account</TableHead>
                                        <TableHead>Bill period</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Original bill</TableHead>
                                        <TableHead className="text-right">Open balance</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {bills.data.length === 0 ? (
                                        <TableRow><TableCell colSpan={6} className="py-10 text-center text-muted-foreground">No eligible bills found for these filters.</TableCell></TableRow>
                                    ) : bills.data.map((bill) => {
                                        const consistent = Math.abs(bill.balance - bill.stored_amount_due) <= 0.01;
                                        return (
                                            <TableRow key={bill.id}>
                                                <TableCell>
                                                    <input
                                                        aria-label={`Select bill ${bill.id}`}
                                                        type="checkbox"
                                                        disabled={!consistent}
                                                        checked={selected.includes(bill.id)}
                                                        onChange={(event) => setSelected((current) => event.target.checked
                                                            ? [...current, bill.id]
                                                            : current.filter((id) => id !== bill.id))}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Link href={route('accounts.show', bill.account.id)} className="font-medium hover:underline">
                                                        {bill.account.name}
                                                    </Link>
                                                    <p className="text-xs text-muted-foreground">{bill.account.account_number} · {bill.account.status}</p>
                                                </TableCell>
                                                <TableCell>
                                                    <Link href={route('billings.show', bill.id)} className="hover:underline">
                                                        {bill.billing_period} · Bill #{bill.id}
                                                    </Link>
                                                </TableCell>
                                                <TableCell><Badge variant="outline" className="capitalize">{bill.status.replace('_', ' ')}</Badge></TableCell>
                                                <TableCell className="text-right">{formatCurrency(bill.total_amount)}</TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {formatCurrency(bill.balance)}
                                                    {!consistent && <p className="text-xs text-red-600">Balance conflict — resolve first</p>}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>

                        {(errors.billing_ids || errors.reason) && (
                            <p className="mt-3 text-sm text-red-600">{errors.billing_ids ?? errors.reason}</p>
                        )}

                        <div className="mt-4 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                            <label className="flex flex-col gap-1 text-sm">
                                Write-off reason / approval reference
                                <textarea
                                    value={reason}
                                    onChange={(event) => setReason(event.target.value)}
                                    minLength={10}
                                    maxLength={2000}
                                    rows={3}
                                    placeholder="Explain why these debts are uncollectible and include approval/reference."
                                    className="rounded-md border bg-background px-3 py-2"
                                />
                            </label>
                            <div className="flex flex-col items-end gap-2">
                                <span className="text-sm text-muted-foreground">{selected.length} selected · {formatCurrency(selectedTotal)}</span>
                                <Button onClick={applyWriteOff} disabled={submitting || selected.length === 0 || reason.trim().length < 10}>
                                    {submitting ? 'Applying…' : 'Apply write-off credit notes'}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {bills.last_page > 1 && (
                    <div className="flex gap-1 justify-center flex-wrap">
                        {bills.links.map((link, index) => (
                            <button
                                key={index}
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`px-3 py-1.5 rounded text-sm border ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
