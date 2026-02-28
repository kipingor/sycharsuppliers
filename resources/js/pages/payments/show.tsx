import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Pencil, Trash, Download, Mail, Receipt } from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { Badge } from '@/components/ui/badge';

interface Account {
    id: number;
    name: string;
    account_number?: string;
    email?: string;
}

interface Bill {
    id: number;
    billing_period: string;
    total_amount: number;
    status: string;
}

interface Allocation {
    id: number;
    billing_id: number;
    allocated_amount: number;
    billing?: Bill;
}

interface Payment {
    id: number;
    account_id: number;
    account?: Account;
    payment_date: string;
    amount: number;
    method: string;
    transaction_id?: string;
    reference?: string;
    status: string;
    reconciliation_status?: string;
    notes?: string;
    created_at: string;
    allocations?: Allocation[];
    unallocated_amount?: number;
}

interface PaymentShowPageProps {
    payment: Payment;
    allocations: Allocation[];
    can: {
        update: boolean;
        delete: boolean;
        reconcile?: boolean;
    };
}

export default function PaymentShow() {
    const { payment, allocations, can } = usePage<SharedData & PaymentShowPageProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Payments', href: route('payments.index') },
        { title: `Payment #${payment.id}`, href: '#' },
    ];

    const statusClasses: Record<string, string> = {
        pending: "bg-yellow-400/20 text-yellow-700 dark:bg-yellow-400/10 dark:text-yellow-300",
        completed: "bg-green-400/20 text-green-700 dark:bg-green-400/10 dark:text-green-300",
        failed: "bg-red-400/20 text-red-700 dark:bg-red-400/10 dark:text-red-300",
        cancelled: "bg-gray-400/20 text-gray-700 dark:bg-gray-400/10 dark:text-gray-300",
    };

    const reconciliationClasses: Record<string, string> = {
        reconciled: "bg-green-400/20 text-green-700",
        pending: "bg-yellow-400/20 text-yellow-700",
        unreconciled: "bg-orange-400/20 text-orange-700",
    };

    const handleDelete = () => {
        if (confirm("Are you sure you want to delete this payment? This will also remove all bill allocations. This action cannot be undone.")) {
            router.delete(route('payments.destroy', payment.id), {
                onSuccess: () => {
                    router.visit(route('payments.index'));
                },
            });
        }
    };

    const handleDownloadReceipt = () => {
        router.get(route('payments.receipt.download', payment.id), {}, {
            preserveState: true,
        });
    };

    const handleEmailReceipt = () => {
        const email = prompt(
            "Enter email address to send receipt:",
            payment.account?.email || ''
        );
        if (email) {
            router.post(route('payments.receipt.send', payment.id), { email });
        }
    };

    const totalAllocated = allocations.reduce((sum, alloc) => sum + Number(alloc.allocated_amount), 0);
    const unallocated = Number(payment.amount) - totalAllocated;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Payment #${payment.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header */}
                <div className="flex justify-between items-start mb-4">
                    <div>
                        <h1 className="text-2xl font-bold">Payment #{payment.id}</h1>
                        <p className="text-gray-500 text-sm mt-1">
                            {new Date(payment.payment_date).toLocaleDateString()}
                        </p>
                    </div>
                    <div className="flex gap-2 flex-wrap justify-end">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleDownloadReceipt}
                        >
                            <Download size={16} className="mr-2" /> Download Receipt
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleEmailReceipt}
                        >
                            <Mail size={16} className="mr-2" /> Email Receipt
                        </Button>
                        {can.update && (
                            <Link href={route('payments.edit', payment.id)}>
                                <Button variant="outline" size="sm">
                                    <Pencil size={16} className="mr-2" /> Edit
                                </Button>
                            </Link>
                        )}
                        {can.delete && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={handleDelete}
                            >
                                <Trash size={16} className="mr-2" /> Delete
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {/* Account Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Account Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Account Name</p>
                                <p className="font-medium">{payment.account?.name || 'N/A'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Account Number</p>
                                <p className="font-medium">{payment.account?.account_number || 'N/A'}</p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Payment Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Amount</p>
                                <p className="font-medium text-lg">{formatCurrency(payment.amount)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Payment Method</p>
                                <p className="font-medium capitalize">{payment.method}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Reference</p>
                                <p className="font-medium">{payment.reference || payment.transaction_id || 'N/A'}</p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Status */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Payment Status</p>
                                <Badge className={statusClasses[payment.status.toLowerCase()] || statusClasses.pending}>
                                    {payment.status}
                                </Badge>
                            </div>
                            {payment.reconciliation_status && (
                                <div>
                                    <p className="text-xs text-gray-500">Reconciliation</p>
                                    <Badge className={reconciliationClasses[payment.reconciliation_status.toLowerCase()]}>
                                        {payment.reconciliation_status}
                                    </Badge>
                                </div>
                            )}
                            <div>
                                <p className="text-xs text-gray-500">Payment Date</p>
                                <p className="font-medium">
                                    {new Date(payment.payment_date).toLocaleDateString()}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Recorded On</p>
                                <p className="font-medium">
                                    {new Date(payment.created_at).toLocaleDateString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Allocation Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Receipt size={20} />
                            Payment Allocation Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-3 gap-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-md mb-4">
                            <div>
                                <p className="text-xs text-gray-500">Total Payment</p>
                                <p className="font-medium text-lg">{formatCurrency(payment.amount)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Allocated</p>
                                <p className="font-medium text-lg">{formatCurrency(totalAllocated)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Unallocated</p>
                                <p className={`font-medium text-lg ${unallocated > 0 ? 'text-yellow-600' : ''}`}>
                                    {formatCurrency(unallocated)}
                                </p>
                            </div>
                        </div>

                        {allocations && allocations.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Bill #</TableHead>
                                        <TableHead>Period</TableHead>
                                        <TableHead className="text-right">Bill Amount</TableHead>
                                        <TableHead className="text-right">Allocated</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {allocations.map((allocation) => (
                                        <TableRow key={allocation.id}>
                                            <TableCell>
                                                <Link 
                                                    href={route('billings.show', allocation.billing_id)}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    #{allocation.billing_id}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {allocation.billing?.billing_period || 'N/A'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {allocation.billing?.total_amount 
                                                    ? formatCurrency(allocation.billing.total_amount)
                                                    : 'N/A'}
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {formatCurrency(allocation.allocated_amount)}
                                            </TableCell>
                                            <TableCell>
                                                {allocation.billing?.status && (
                                                    <Badge className="capitalize">
                                                        {allocation.billing.status.replace('_', ' ')}
                                                    </Badge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-center py-8">
                                <p className="text-gray-500">No allocations yet. Payment is unallocated.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Notes */}
                {payment.notes && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm whitespace-pre-wrap">{payment.notes}</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}