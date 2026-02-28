import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Pencil, Trash, XCircle, RotateCcw, Download, Mail, FileMinus, AlertTriangle } from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { Badge } from '@/components/ui/badge';
import { useState } from 'react';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Account {
    id: number;
    name: string;
    account_number?: string;
    email?: string;
}

interface BillingDetail {
    id: number;
    billing_id: number;
    meter_id: number;
    previous_reading: number;
    current_reading: number;
    units: number;
    rate: number;
    amount: number;
    description?: string;
    meter?: { id: number; meter_number: string; meter_name: string; status: string };
}

interface PaymentAllocation {
    id: number;
    payment_id: number;
    allocated_amount: number;
    payment?: {
        id: number;
        reference?: string;
        payment_date: string;
        amount: number;
        method: string;
    };
}

interface CreditNote {
    id: number;
    reference: string;
    type: string;
    amount: number;
    reason: string;
    status: 'applied' | 'voided';
    void_reason?: string;
    voided_at?: string;
    created_at: string;
    created_by?: { id: number; name: string };
    previous_account?: { id: number; name: string; account_number?: string };
}

interface Bill {
    id: number;
    account_id: number;
    billing_period: string;
    total_amount: number;
    paid_amount: number;
    balance: number;
    status: 'pending' | 'partially_paid' | 'paid' | 'overdue' | 'voided';
    issued_at: string;
    due_date: string;
    paid_at?: string;
    notes?: string;
    account?: Account;
    details?: BillingDetail[];
    allocations?: PaymentAllocation[];
    credit_notes?: CreditNote[];
    summary?: {
        id: number;
        billing_period: string;
        formatted_period: string;
        total_amount: number;
        paid_amount: number;
        balance: number;
        status: string;
        issued_at: string;
        due_date: string;
        days_until_due: number;
        is_overdue: boolean;
        days_overdue: number;
    };
    can_be_modified?: boolean;
    is_overdue?: boolean;
    days_until_due?: number;
    days_overdue?: number;
}

interface BillShowPageProps {
    bill: Bill;
    can: { update: boolean; delete: boolean; void: boolean; rebill: boolean };
    accounts?: Account[];  // for previous-resident lookup
}

// ─── Credit Note Modal ────────────────────────────────────────────────────────

const CREDIT_NOTE_TYPES = [
    { value: 'previous_resident_debt', label: 'Previous Resident Debt' },
    { value: 'billing_error',          label: 'Billing Error'           },
    { value: 'goodwill',               label: 'Goodwill Adjustment'     },
    { value: 'other',                  label: 'Other'                   },
];

function CreditNoteModal({
    bill,
    accounts,
    onClose,
}: {
    bill: Bill;
    accounts: Account[];
    onClose: () => void;
}) {
    const [form, setForm] = useState({
        type:                'previous_resident_debt',
        amount:              '',
        reason:              '',
        previous_account_id: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const maxAmount = bill.balance;

    const handleSubmit = () => {
        const errs: Record<string, string> = {};
        if (!form.amount || isNaN(Number(form.amount)) || Number(form.amount) <= 0) {
            errs.amount = 'Enter a valid amount greater than 0.';
        }
        if (Number(form.amount) > maxAmount) {
            errs.amount = `Cannot exceed the current balance of ${formatCurrency(maxAmount)}.`;
        }
        if (!form.reason || form.reason.trim().length < 10) {
            errs.reason = 'Reason must be at least 10 characters.';
        }
        if (Object.keys(errs).length) { setErrors(errs); return; }

        setSubmitting(true);
        router.post(
            route('billings.credit-notes.store', bill.id),
            {
                type:                form.type,
                amount:              Number(form.amount),
                reason:              form.reason.trim(),
                previous_account_id: form.previous_account_id || null,
            },
            {
                onError:  (e) => { setErrors(e); setSubmitting(false); },
                onSuccess: () => { onClose(); },
            },
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="bg-background rounded-xl shadow-2xl w-full max-w-lg">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b">
                    <div>
                        <h2 className="text-lg font-semibold flex items-center gap-2">
                            <FileMinus size={18} className="text-amber-500" />
                            Apply Credit Note
                        </h2>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Bill #{bill.id} · Balance: <span className="font-medium">{formatCurrency(maxAmount)}</span>
                        </p>
                    </div>
                    <button onClick={onClose} className="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
                </div>

                {/* Warning banner for previous resident scenario */}
                {form.type === 'previous_resident_debt' && (
                    <div className="mx-6 mt-4 flex gap-2 text-sm bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg p-3">
                        <AlertTriangle size={16} className="text-amber-600 shrink-0 mt-0.5" />
                        <p className="text-amber-800 dark:text-amber-300">
                            This will remove the previous resident's unpaid debt from the current bill so the new resident is only charged for their own usage.
                        </p>
                    </div>
                )}

                {/* Form */}
                <div className="px-6 py-4 space-y-4">
                    {/* Type */}
                    <div>
                        <label className="block text-sm font-medium mb-1">Credit Note Type</label>
                        <select
                            value={form.type}
                            onChange={e => setForm(f => ({ ...f, type: e.target.value }))}
                            className="w-full border rounded-md px-3 py-2 text-sm bg-background"
                        >
                            {CREDIT_NOTE_TYPES.map(t => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                            ))}
                        </select>
                    </div>

                    {/* Previous resident account (shown when relevant) */}
                    {form.type === 'previous_resident_debt' && accounts.length > 0 && (
                        <div>
                            <label className="block text-sm font-medium mb-1">
                                Previous Resident Account <span className="text-muted-foreground font-normal">(optional)</span>
                            </label>
                            <select
                                value={form.previous_account_id}
                                onChange={e => setForm(f => ({ ...f, previous_account_id: e.target.value }))}
                                className="w-full border rounded-md px-3 py-2 text-sm bg-background"
                            >
                                <option value="">— Select if known —</option>
                                {accounts.map(a => (
                                    <option key={a.id} value={a.id}>
                                        {a.name}{a.account_number ? ` (${a.account_number})` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Amount */}
                    <div>
                        <label className="block text-sm font-medium mb-1">
                            Credit Amount
                        </label>
                        <div className="relative">
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                max={maxAmount}
                                value={form.amount}
                                onChange={e => setForm(f => ({ ...f, amount: e.target.value }))}
                                placeholder={`Max ${formatCurrency(maxAmount)}`}
                                className="w-full border rounded-md px-3 py-2 text-sm bg-background"
                            />
                            {/* Quick-fill button */}
                            <button
                                type="button"
                                onClick={() => setForm(f => ({ ...f, amount: maxAmount.toString() }))}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-blue-600 hover:underline"
                            >
                                Full balance
                            </button>
                        </div>
                        {errors.amount && <p className="text-red-500 text-xs mt-1">{errors.amount}</p>}
                        <p className="text-xs text-muted-foreground mt-1">
                            New balance after credit: <strong>{formatCurrency(Math.max(0, maxAmount - (Number(form.amount) || 0)))}</strong>
                        </p>
                    </div>

                    {/* Reason */}
                    <div>
                        <label className="block text-sm font-medium mb-1">Reason / Notes</label>
                        <textarea
                            rows={3}
                            value={form.reason}
                            onChange={e => setForm(f => ({ ...f, reason: e.target.value }))}
                            placeholder="e.g. Removing outstanding balance from previous resident John Doe who vacated on 01 Jan 2025."
                            className="w-full border rounded-md px-3 py-2 text-sm bg-background resize-none"
                        />
                        {errors.reason && <p className="text-red-500 text-xs mt-1">{errors.reason}</p>}
                    </div>
                </div>

                {/* Actions */}
                <div className="flex justify-end gap-2 px-6 py-4 border-t">
                    <Button variant="outline" onClick={onClose} disabled={submitting}>Cancel</Button>
                    <Button onClick={handleSubmit} disabled={submitting} className="gap-2">
                        <FileMinus size={15} />
                        {submitting ? 'Applying…' : 'Apply Credit Note'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

// ─── Void Credit Note Modal ───────────────────────────────────────────────────

function VoidModal({
    note,
    onClose,
}: {
    note: CreditNote;
    onClose: () => void;
}) {
    const [reason, setReason] = useState('');
    const [error,  setError]  = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleVoid = () => {
        if (reason.trim().length < 10) { setError('Reason must be at least 10 characters.'); return; }
        setSubmitting(true);
        router.post(
            route('credit-notes.void', note.id),
            { void_reason: reason.trim() },
            {
                onError:   (e) => { setError(e.void_reason ?? 'An error occurred.'); setSubmitting(false); },
                onSuccess: () => onClose(),
            },
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="bg-background rounded-xl shadow-2xl w-full max-w-md">
                <div className="px-6 py-4 border-b">
                    <h2 className="font-semibold">Void Credit Note {note.reference}</h2>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        This will reverse the {formatCurrency(note.amount)} credit and restore the billing balance.
                    </p>
                </div>
                <div className="px-6 py-4">
                    <label className="block text-sm font-medium mb-1">Reason for voiding</label>
                    <textarea
                        rows={3}
                        value={reason}
                        onChange={e => setReason(e.target.value)}
                        className="w-full border rounded-md px-3 py-2 text-sm bg-background resize-none"
                        placeholder="Explain why this credit note is being voided…"
                    />
                    {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
                </div>
                <div className="flex justify-end gap-2 px-6 py-4 border-t">
                    <Button variant="outline" onClick={onClose} disabled={submitting}>Cancel</Button>
                    <Button variant="destructive" onClick={handleVoid} disabled={submitting}>
                        {submitting ? 'Voiding…' : 'Void Credit Note'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function BillShow() {
    const { bill, can, accounts = [] } = usePage<SharedData & BillShowPageProps>().props;

    const [showCreditModal, setShowCreditModal] = useState(false);
    const [voidingNote,     setVoidingNote]     = useState<CreditNote | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Bills', href: route('billings.index') },
        { title: `Bill #${bill.id}`, href: '#' },
    ];

    const statusClasses: Record<string, string> = {
        paid:           'bg-lime-400/20 text-lime-700 dark:bg-lime-400/10 dark:text-lime-300',
        pending:        'bg-sky-400/15 text-sky-700 dark:bg-sky-400/10 dark:text-sky-400',
        overdue:        'bg-pink-400/15 text-pink-700 dark:bg-pink-400/10 dark:text-pink-400',
        partially_paid: 'bg-blue-400/15 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400',
        voided:         'bg-gray-400/15 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400',
    };

    const creditNoteTypeClasses: Record<string, string> = {
        previous_resident_debt: 'bg-amber-400/15 text-amber-700 dark:text-amber-400',
        billing_error:          'bg-red-400/15 text-red-700 dark:text-red-400',
        goodwill:               'bg-purple-400/15 text-purple-700 dark:text-purple-400',
        other:                  'bg-gray-400/15 text-gray-700 dark:text-gray-400',
    };

    const creditNoteTypeLabels: Record<string, string> = {
        previous_resident_debt: 'Prev. Resident Debt',
        billing_error:          'Billing Error',
        goodwill:               'Goodwill',
        other:                  'Other',
    };

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this bill? This action cannot be undone.')) {
            router.delete(route('billings.destroy', bill.id), {
                onSuccess: () => router.visit(route('billings.index')),
            });
        }
    };

    const handleVoid = () => {
        const reason = prompt('Please provide a reason for voiding this bill:');
        if (reason && reason.trim().length >= 10) {
            router.post(route('billings.void', bill.id), { reason });
        } else if (reason) {
            alert('Reason must be at least 10 characters long');
        }
    };

    const handleRebill = () => {
        const reason = prompt('Please provide a reason for rebilling:');
        if (reason && reason.trim().length >= 10) {
            router.post(route('billings.rebill', bill.id), { reason });
        } else if (reason) {
            alert('Reason must be at least 10 characters long');
        }
    };

    const handleDownloadStatement = () => {
        window.location.href = route('billings.statement.download', bill.id);
    };

    const handleEmailStatement = () => {
        const email = prompt('Enter email address to send statement:', bill.account?.email || '');
        if (email) {
            router.post(route('billings.statement.send', bill.id), { email });
        }
    };

    const canApplyCreditNote = bill.status !== 'voided' && bill.status !== 'paid' && bill.balance > 0;
    const appliedCreditNotes = (bill.credit_notes ?? []).filter(n => n.status === 'applied');
    const totalCredited      = appliedCreditNotes.reduce((s, n) => s + n.amount, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Bill #${bill.id}`} />

            {showCreditModal && (
                <CreditNoteModal
                    bill={bill}
                    accounts={accounts}
                    onClose={() => setShowCreditModal(false)}
                />
            )}

            {voidingNote && (
                <VoidModal
                    note={voidingNote}
                    onClose={() => setVoidingNote(null)}
                />
            )}

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header */}
                <div className="flex justify-between items-start mb-4">
                    <div>
                        <h1 className="text-2xl font-bold">Bill #{bill.id}</h1>
                        <p className="text-gray-500 text-sm mt-1">
                            {bill.summary?.formatted_period || bill.billing_period}
                        </p>
                    </div>
                    <div className="flex gap-2 flex-wrap justify-end">
                        <Button variant="outline" size="sm" onClick={handleDownloadStatement}>
                            <Download size={16} className="mr-2" /> Download
                        </Button>
                        <Button variant="outline" size="sm" onClick={handleEmailStatement}>
                            <Mail size={16} className="mr-2" /> Email
                        </Button>
                        {canApplyCreditNote && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShowCreditModal(true)}
                                className="border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-400 dark:hover:bg-amber-950/30"
                            >
                                <FileMinus size={16} className="mr-2" /> Credit Note
                            </Button>
                        )}
                        {can.update && bill.can_be_modified && (
                            <Link href={route('billings.edit', bill.id)}>
                                <Button variant="outline" size="sm">
                                    <Pencil size={16} className="mr-2" /> Edit
                                </Button>
                            </Link>
                        )}
                        {can.void && bill.can_be_modified && (
                            <Button variant="outline" size="sm" onClick={handleVoid}>
                                <XCircle size={16} className="mr-2" /> Void
                            </Button>
                        )}
                        {can.rebill && (
                            <Button variant="outline" size="sm" onClick={handleRebill}>
                                <RotateCcw size={16} className="mr-2" /> Rebill
                            </Button>
                        )}
                        {can.delete && bill.can_be_modified && (
                            <Button variant="destructive" size="sm" onClick={handleDelete}>
                                <Trash size={16} className="mr-2" /> Delete
                            </Button>
                        )}
                    </div>
                </div>

                {/* Previous resident debt banner */}
                {bill.status !== 'paid' && bill.status !== 'voided' && totalCredited > 0 && (
                    <div className="flex items-start gap-3 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 rounded-lg px-4 py-3">
                        <AlertTriangle size={16} className="text-amber-600 shrink-0 mt-0.5" />
                        <p className="text-sm text-amber-800 dark:text-amber-300">
                            <strong>{formatCurrency(totalCredited)}</strong> in credit notes have been applied to this bill.
                            The balance shown reflects only the current resident's charges.
                        </p>
                    </div>
                )}

                {/* Summary cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader><CardTitle>Account Information</CardTitle></CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Account Name</p>
                                <p className="font-medium">{bill.account?.name || 'N/A'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Account Number</p>
                                <p className="font-medium">{bill.account?.account_number || 'N/A'}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Bill Summary</CardTitle></CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Total Amount</p>
                                <p className="font-medium text-lg">{formatCurrency(bill.total_amount)}</p>
                            </div>
                            {totalCredited > 0 && (
                                <div>
                                    <p className="text-xs text-amber-600">Credits Applied</p>
                                    <p className="font-medium text-amber-600">− {formatCurrency(totalCredited)}</p>
                                </div>
                            )}
                            <div>
                                <p className="text-xs text-gray-500">Paid Amount</p>
                                <p className="font-medium">{formatCurrency(bill.paid_amount || 0)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Balance Due</p>
                                <p className="font-medium text-lg">{formatCurrency(bill.balance || bill.total_amount)}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Status & Dates</CardTitle></CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Status</p>
                                <Badge className={statusClasses[bill.status]}>
                                    {bill.status.replace('_', ' ')}
                                </Badge>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Issued Date</p>
                                <p className="font-medium">{new Date(bill.issued_at).toLocaleDateString()}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Due Date</p>
                                <p className="font-medium">{new Date(bill.due_date).toLocaleDateString()}</p>
                            </div>
                            {bill.is_overdue && bill.days_overdue && (
                                <div>
                                    <p className="text-xs text-red-600">Overdue</p>
                                    <p className="font-medium text-red-600">{bill.days_overdue} days</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Billing Details */}
                {bill.details && bill.details.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle>Billing Details</CardTitle></CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Meter</TableHead>
                                        <TableHead className="text-right">Previous</TableHead>
                                        <TableHead className="text-right">Current</TableHead>
                                        <TableHead className="text-right">Units</TableHead>
                                        <TableHead className="text-right">Rate</TableHead>
                                        <TableHead className="text-right">Amount</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {bill.details.map(detail => (
                                        <TableRow key={detail.id}>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium text-sm">{detail.meter?.meter_number || 'N/A'}</p>
                                                    <p className="text-xs text-gray-500">{detail.meter?.meter_name}</p>
                                                    {detail.description && (
                                                        <p className="text-xs text-gray-400">{detail.description}</p>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">{detail.previous_reading}</TableCell>
                                            <TableCell className="text-right">{detail.current_reading}</TableCell>
                                            <TableCell className="text-right font-medium">{detail.units}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(detail.rate)}</TableCell>
                                            <TableCell className="text-right font-medium">{formatCurrency(detail.amount)}</TableCell>
                                        </TableRow>
                                    ))}
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-right font-bold">Total</TableCell>
                                        <TableCell className="text-right font-bold">{formatCurrency(bill.total_amount)}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* Credit Notes */}
                {(bill.credit_notes ?? []).length > 0 && (
                    <Card className="border-amber-200 dark:border-amber-800">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileMinus size={16} className="text-amber-500" />
                                Credit Notes
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Reference</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Previous Resident</TableHead>
                                        <TableHead>Reason</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Amount</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {(bill.credit_notes ?? []).map(note => (
                                        <TableRow key={note.id} className={note.status === 'voided' ? 'opacity-50' : ''}>
                                            <TableCell className="font-mono text-sm">{note.reference}</TableCell>
                                            <TableCell>
                                                <Badge className={creditNoteTypeClasses[note.type] ?? 'bg-gray-100'}>
                                                    {creditNoteTypeLabels[note.type] ?? note.type}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {note.previous_account
                                                    ? `${note.previous_account.name}${note.previous_account.account_number ? ` (${note.previous_account.account_number})` : ''}`
                                                    : <span className="text-muted-foreground">—</span>
                                                }
                                            </TableCell>
                                            <TableCell className="text-sm max-w-[200px] truncate" title={note.reason}>
                                                {note.reason}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {new Date(note.created_at).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <Badge className={
                                                    note.status === 'applied'
                                                        ? 'bg-lime-400/20 text-lime-700 dark:text-lime-300'
                                                        : 'bg-gray-400/15 text-gray-600'
                                                }>
                                                    {note.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right font-medium text-amber-600">
                                                − {formatCurrency(note.amount)}
                                            </TableCell>
                                            <TableCell>
                                                {note.status === 'applied' && (
                                                    <button
                                                        onClick={() => setVoidingNote(note)}
                                                        className="text-xs text-red-500 hover:underline whitespace-nowrap"
                                                    >
                                                        Void
                                                    </button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* Payment Allocations */}
                {bill.allocations && bill.allocations.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle>Payment History</CardTitle></CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Reference</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead className="text-right">Amount Allocated</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {bill.allocations.map(allocation => (
                                        <TableRow key={allocation.id}>
                                            <TableCell>
                                                {allocation.payment?.payment_date
                                                    ? new Date(allocation.payment.payment_date).toLocaleDateString()
                                                    : 'N/A'}
                                            </TableCell>
                                            <TableCell>{allocation.payment?.reference || 'N/A'}</TableCell>
                                            <TableCell className="capitalize">{allocation.payment?.method || 'N/A'}</TableCell>
                                            <TableCell className="text-right font-medium">
                                                {formatCurrency(allocation.allocated_amount)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    <TableRow>
                                        <TableCell colSpan={3} className="text-right font-bold">Total Paid</TableCell>
                                        <TableCell className="text-right font-bold">
                                            {formatCurrency(bill.paid_amount || 0)}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* Notes */}
                {bill.notes && (
                    <Card>
                        <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
                        <CardContent>
                            <p className="text-sm whitespace-pre-wrap">{bill.notes}</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}