import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/utils';
import { Pencil, Trash, CheckCircle, XCircle, FileText, Paperclip } from 'lucide-react';
import { useState } from 'react';

interface Expense {
    id: number; category: string; description: string; amount: number;
    expense_date: string; receipt_number?: string; receipt_path?: string;
    status: boolean; approved_by?: number; rejected_by?: number;
    rejected_at?: string; rejection_reason?: string;
    approver?: { id: number; name: string };
    rejector?: { id: number; name: string };
    created_at: string;
}

interface Budget {
    monthly_limit: number; spent: number; remaining: number; percent_used: number; is_over: boolean;
}

interface ExpenseShowProps {
    expense: Expense;
    budget: Budget | null;
    can: { update: boolean; delete: boolean; approve: boolean; reject: boolean };
}

export default function ExpenseShow() {
    const { expense, budget, can } = usePage<SharedData & ExpenseShowProps>().props;
    const [showRejectForm, setShowRejectForm] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Expenses', href: route('expenses.index') },
        { title: `Expense #${expense.id}`, href: '#' },
    ];

    const rejectForm = useForm({ reason: '' });

    const isApproved = expense.status && expense.approved_by;
    const isRejected = !expense.status && expense.rejected_by;
    const isPending  = !expense.status && !expense.rejected_by;

    const handleApprove = () => {
        if (confirm('Approve this expense?'))
            router.post(route('expenses.approve', expense.id), {}, { preserveScroll: true });
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        rejectForm.post(route('expenses.reject', expense.id), {
            preserveScroll: true,
            onSuccess: () => setShowRejectForm(false),
        });
    };

    const handleDelete = () => {
        if (confirm('Permanently delete this expense?'))
            router.delete(route('expenses.destroy', expense.id));
    };

    const statusBadge = isApproved
        ? <Badge className="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">Approved</Badge>
        : isRejected
            ? <Badge className="bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">Rejected</Badge>
            : <Badge className="bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300">Pending Approval</Badge>;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Expense #${expense.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Expense #{expense.id}</h1>
                        <p className="text-sm text-muted-foreground mt-1">{expense.category} · {new Date(expense.expense_date).toLocaleDateString()}</p>
                    </div>
                    <div className="flex gap-2 flex-wrap justify-end">
                        {can.approve && isPending && (
                            <Button size="sm" onClick={handleApprove} className="bg-green-600 hover:bg-green-700">
                                <CheckCircle size={16} className="mr-2" />Approve
                            </Button>
                        )}
                        {can.reject && !isRejected && (
                            <Button size="sm" variant="outline" onClick={() => setShowRejectForm(!showRejectForm)} className="border-red-300 text-red-600 hover:bg-red-50">
                                <XCircle size={16} className="mr-2" />Reject
                            </Button>
                        )}
                        {can.update && (
                            <Link href={route('expenses.edit', expense.id)}>
                                <Button size="sm" variant="outline"><Pencil size={16} className="mr-2" />Edit</Button>
                            </Link>
                        )}
                        {can.delete && (
                            <Button size="sm" variant="destructive" onClick={handleDelete}>
                                <Trash size={16} className="mr-2" />Delete
                            </Button>
                        )}
                    </div>
                </div>

                {/* Reject form */}
                {showRejectForm && (
                    <Card className="border-red-200 bg-red-50 dark:bg-red-950/20">
                        <CardContent className="pt-4">
                            <form onSubmit={handleReject} className="space-y-3">
                                <Label htmlFor="reason">Rejection reason (optional)</Label>
                                <Textarea id="reason" value={rejectForm.data.reason}
                                    onChange={(e) => rejectForm.setData('reason', e.target.value)}
                                    placeholder="Explain why this expense is being rejected..." rows={2} />
                                <div className="flex gap-2">
                                    <Button type="submit" size="sm" variant="destructive" disabled={rejectForm.processing}>
                                        Confirm Rejection
                                    </Button>
                                    <Button type="button" size="sm" variant="outline" onClick={() => setShowRejectForm(false)}>Cancel</Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {/* Details */}
                    <Card className="md:col-span-2">
                        <CardHeader><CardTitle>Expense Details</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div><p className="text-xs text-muted-foreground">Amount</p><p className="text-2xl font-bold">{formatCurrency(Number(expense.amount))}</p></div>
                                <div><p className="text-xs text-muted-foreground">Status</p><div className="mt-1">{statusBadge}</div></div>
                                <div><p className="text-xs text-muted-foreground">Category</p><p className="font-medium">{expense.category}</p></div>
                                <div><p className="text-xs text-muted-foreground">Date</p><p className="font-medium">{new Date(expense.expense_date).toLocaleDateString()}</p></div>
                                {expense.receipt_number && <div><p className="text-xs text-muted-foreground">Receipt #</p><p className="font-medium">{expense.receipt_number}</p></div>}
                                <div><p className="text-xs text-muted-foreground">Recorded</p><p className="font-medium">{new Date(expense.created_at).toLocaleDateString()}</p></div>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground mb-1">Description</p>
                                <p className="text-sm whitespace-pre-wrap bg-muted/40 rounded p-3">{expense.description}</p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Sidebar */}
                    <div className="flex flex-col gap-4">
                        {/* Approval info */}
                        <Card>
                            <CardHeader><CardTitle className="text-base">Approval</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                {isApproved && expense.approver && (
                                    <div>
                                        <p className="text-xs text-muted-foreground">Approved by</p>
                                        <p className="font-medium text-green-700 dark:text-green-400">{expense.approver.name}</p>
                                    </div>
                                )}
                                {isRejected && (
                                    <>
                                        <div>
                                            <p className="text-xs text-muted-foreground">Rejected by</p>
                                            <p className="font-medium text-red-700 dark:text-red-400">{expense.rejector?.name}</p>
                                        </div>
                                        {expense.rejected_at && (
                                            <div>
                                                <p className="text-xs text-muted-foreground">Rejected on</p>
                                                <p className="text-sm">{new Date(expense.rejected_at).toLocaleDateString()}</p>
                                            </div>
                                        )}
                                        {expense.rejection_reason && (
                                            <div>
                                                <p className="text-xs text-muted-foreground">Reason</p>
                                                <p className="text-sm bg-red-50 dark:bg-red-950/30 rounded p-2">{expense.rejection_reason}</p>
                                            </div>
                                        )}
                                    </>
                                )}
                                {isPending && <p className="text-sm text-muted-foreground">Awaiting admin approval.</p>}
                            </CardContent>
                        </Card>

                        {/* Budget */}
                        {budget && (
                            <Card className={budget.is_over ? 'border-red-300' : ''}>
                                <CardHeader><CardTitle className="text-base">Budget — {expense.category}</CardTitle></CardHeader>
                                <CardContent className="space-y-2">
                                    <Progress value={Math.min(budget.percent_used, 100)}
                                        className={`h-2 ${budget.is_over ? '[&>*]:bg-red-500' : ''}`} />
                                    <div className="flex justify-between text-xs text-muted-foreground">
                                        <span>Spent: {formatCurrency(budget.spent)}</span>
                                        <span>Limit: {formatCurrency(budget.monthly_limit)}</span>
                                    </div>
                                    {budget.is_over && (
                                        <p className="text-xs text-red-600 font-medium">Over budget by {formatCurrency(Math.abs(budget.remaining))}</p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Receipt file */}
                        {expense.receipt_path && (
                            <Card>
                                <CardHeader><CardTitle className="text-base">Receipt</CardTitle></CardHeader>
                                <CardContent>
                                    {expense.receipt_path.match(/\.(jpg|jpeg|png)$/i) ? (
                                        <img src={`/storage/${expense.receipt_path}`} alt="Receipt"
                                            className="rounded border w-full object-contain max-h-48" />
                                    ) : (
                                        <a href={`/storage/${expense.receipt_path}`} target="_blank" rel="noreferrer"
                                            className="flex items-center gap-2 text-sm text-blue-600 hover:underline">
                                            <FileText size={16} /><Paperclip size={14} />View receipt PDF
                                        </a>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}