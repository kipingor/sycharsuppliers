import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Pencil, Trash, XCircle, RotateCcw, Download, Mail } from 'lucide-react';
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

interface Meter {
    id: number;
    meter_number: string;
    meter_name: string;
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
    meter?: {
        id: number;
        meter_number: string;
        meter_name: string;
        status: string;
    };
}

interface PaymentAllocation {
    id: number;
    payment_id: number;
    amount_allocated: number;
    payment?: {
        id: number;
        reference?: string;
        payment_date: string;
        amount: number;
        method: string;
    };
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
        detail_count: number;
        allocation_count: number;
    };
    can_be_modified?: boolean;
    is_overdue?: boolean;
    days_until_due?: number;
    days_overdue?: number;
}

interface BillShowPageProps {
    bill: Bill;
    can: {
        update: boolean;
        delete: boolean;
        void: boolean;
        rebill: boolean;
    };
}

export default function BillShow() {
    const { bill, can, auth } = usePage<SharedData & BillShowPageProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Bills', href: route('billings.index') },
        { title: `Bill #${bill.id}`, href: '#' },
    ];

    const statusClasses = {
        paid: "bg-lime-400/20 text-lime-700 dark:bg-lime-400/10 dark:text-lime-300",
        pending: "bg-sky-400/15 text-sky-700 dark:bg-sky-400/10 dark:text-sky-400",
        overdue: "bg-pink-400/15 text-pink-700 dark:bg-pink-400/10 dark:text-pink-400",
        partially_paid: "bg-blue-400/15 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400",
        voided: "bg-gray-400/15 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400",
    };

    const handleDelete = () => {
        if (confirm("Are you sure you want to delete this bill? This action cannot be undone.")) {
            router.delete(route('billings.destroy', bill.id), {
                onSuccess: () => {
                    router.visit(route('billings.index'));
                },
            });
        }
    };

    const handleVoid = () => {
        const reason = prompt("Please provide a reason for voiding this bill:");
        if (reason && reason.trim().length >= 10) {
            router.post(route('billings.void', bill.id), { reason });
        } else if (reason) {
            alert('Reason must be at least 10 characters long');
        }
    };

    const handleRebill = () => {
        const reason = prompt("Please provide a reason for rebilling:");
        if (reason && reason.trim().length >= 10) {
            router.post(route('billings.rebill', bill.id), { reason });
        } else if (reason) {
            alert('Reason must be at least 10 characters long');
        }
    };

    const handleDownloadStatement = () => {
        router.get(route('billings.statement.download', bill.id), {}, {
            preserveState: true,
        });
    };

    const handleEmailStatement = () => {
        const email = prompt(
            "Enter email address to send statement:",
            bill.account?.email || ''
        );
        if (email) {
            router.post(route('billings.statement.send', bill.id), { email });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Bill #${bill.id}`} />
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
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleDownloadStatement}
                        >
                            <Download size={16} className="mr-2" /> Download
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleEmailStatement}
                        >
                            <Mail size={16} className="mr-2" /> Email
                        </Button>
                        {can.update && bill.can_be_modified && (
                            <Link href={route('billings.edit', bill.id)}>
                                <Button variant="outline" size="sm">
                                    <Pencil size={16} className="mr-2" /> Edit
                                </Button>
                            </Link>
                        )}
                        {can.void && bill.can_be_modified && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleVoid}
                            >
                                <XCircle size={16} className="mr-2" /> Void
                            </Button>
                        )}
                        {can.rebill && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleRebill}
                            >
                                <RotateCcw size={16} className="mr-2" /> Rebill
                            </Button>
                        )}
                        {can.delete && bill.can_be_modified && (
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
                                <p className="font-medium">{bill.account?.name || 'N/A'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Account Number</p>
                                <p className="font-medium">{bill.account?.account_number || 'N/A'}</p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Bill Summary */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Bill Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Total Amount</p>
                                <p className="font-medium text-lg">{formatCurrency(bill.total_amount)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Paid Amount</p>
                                <p className="font-medium">{formatCurrency(bill.paid_amount || 0)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Balance</p>
                                <p className="font-medium text-lg">{formatCurrency(bill.balance || bill.total_amount)}</p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Status & Dates */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Status & Dates</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Status</p>
                                <Badge className={statusClasses[bill.status]}>
                                    {bill.status.replace('_', ' ')}
                                </Badge>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Issued Date</p>
                                <p className="font-medium">
                                    {new Date(bill.issued_at).toLocaleDateString()}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Due Date</p>
                                <p className="font-medium">
                                    {new Date(bill.due_date).toLocaleDateString()}
                                </p>
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
                        <CardHeader>
                            <CardTitle>Billing Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHead>
                                    <TableRow>
                                        <TableCell>Meter</TableCell>
                                        <TableCell className="text-right">Previous</TableCell>
                                        <TableCell className="text-right">Current</TableCell>
                                        <TableCell className="text-right">Units</TableCell>
                                        <TableCell className="text-right">Rate</TableCell>
                                        <TableCell className="text-right">Amount</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {bill.details.map((detail) => (
                                        <TableRow key={detail.id}>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium text-sm">
                                                        {detail.meter?.meter_number || 'N/A'}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {detail.meter?.meter_name}
                                                    </p>
                                                    {detail.description && (
                                                        <p className="text-xs text-gray-400">
                                                            {detail.description}
                                                        </p>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">{detail.previous_reading}</TableCell>
                                            <TableCell className="text-right">{detail.current_reading}</TableCell>
                                            <TableCell className="text-right font-medium">{detail.units}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(detail.rate)}</TableCell>
                                            <TableCell className="text-right font-medium">
                                                {formatCurrency(detail.amount)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    <TableRow>
                                        <TableCell className="text-right font-bold">Total</TableCell>
                                        <TableCell className="text-right font-bold">
                                            {formatCurrency(bill.total_amount)}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* Payment Allocations */}
                {bill.allocations && bill.allocations.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHead>
                                    <TableRow>
                                        <TableCell>Date</TableCell>
                                        <TableCell>Reference</TableCell>
                                        <TableCell>Method</TableCell>
                                        <TableCell className="text-right">Amount Allocated</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {bill.allocations.map((allocation) => (
                                        <TableRow key={allocation.id}>
                                            <TableCell>
                                                {allocation.payment?.payment_date 
                                                    ? new Date(allocation.payment.payment_date).toLocaleDateString()
                                                    : 'N/A'}
                                            </TableCell>
                                            <TableCell>{allocation.payment?.reference || 'N/A'}</TableCell>
                                            <TableCell className="capitalize">
                                                {allocation.payment?.method || 'N/A'}
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {formatCurrency(allocation.amount_allocated)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    <TableRow>
                                        <TableCell className="text-right font-bold">Total Paid</TableCell>
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
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm whitespace-pre-wrap">{bill.notes}</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}