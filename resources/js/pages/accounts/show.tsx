import { Table, TableBody, TableCell, TableHead, TableRow } from '@/components/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CreditCard, FileText, Gauge, Mail, MapPin, Pencil, Phone, Trash } from 'lucide-react';
import { useState } from 'react';

interface Meter {
    id: number;
    meter_number: string;
    meter_name: string;
    status: string;
    type?: string;
}

interface Billing {
    id: number;
    billing_period: string;
    total_amount: number;
    status: string;
    issued_at: string;
    due_date: string;
}

interface Payment {
    id: number;
    amount: number;
    payment_date: string;
    method: string;
    reference?: string;
    status: string;
}

interface Account {
    id: number;
    account_number: string;
    name: string;
    email?: string;
    phone?: string;
    address?: string;
    status: 'active' | 'suspended' | 'inactive';
    activated_at?: string;
    suspended_at?: string;
    meters: Meter[];
    billings: Billing[];
    payments: Payment[];
}

interface ShowAccountProps {
    account: Account;
    balance: number;
    can: { update: boolean; delete: boolean };
}

const statusBadge: Record<string, string> = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    suspended: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    inactive: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    paid: 'bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-400',
    pending: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
    partially_paid: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    voided: 'bg-gray-100 text-gray-500',
    completed: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
};

export default function ShowAccount() {
    const { account, balance, can } = usePage<SharedData & ShowAccountProps>().props;
    const [activeTab, setActiveTab] = useState('meters');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Accounts', href: route('accounts.index') },
        { title: account.name, href: '#' },
    ];

    const handleDelete = () => {
        if (confirm(`Delete account "${account.name}"? This action cannot be undone.`)) {
            router.delete(route('accounts.destroy', account.id));
        }
    };

    const outstandingCount = account.billings.filter((b) => ['pending', 'partially_paid', 'overdue'].includes(b.status)).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Account — ${account.name}`} />
            <div className="mx-auto flex h-full max-w-5xl flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-3">
                        <Link href={route('accounts.index')}>
                            <Button variant="ghost" size="sm">
                                <ArrowLeft size={16} />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold">{account.name}</h1>
                            <p className="text-muted-foreground mt-0.5 font-mono text-sm">{account.account_number}</p>
                        </div>
                        <Badge className={statusBadge[account.status]}>{account.status}</Badge>
                    </div>
                    <div className="flex gap-2">
                        {can.update && (
                            <Link href={route('accounts.edit', account.id)}>
                                <Button variant="outline" size="sm">
                                    <Pencil size={14} className="mr-1.5" /> Edit
                                </Button>
                            </Link>
                        )}
                        {can.delete && (
                            <Button variant="destructive" size="sm" onClick={handleDelete}>
                                <Trash size={14} className="mr-1.5" /> Delete
                            </Button>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href={route('accounts.statement', account.id)}>
                            <Button variant="outline" size="sm">
                                <FileText size={16} className="mr-2" /> Statement
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="pt-5">
                            <p className="text-muted-foreground text-xs">Outstanding Balance</p>
                            <p className={`mt-0.5 text-xl font-bold ${balance > 0 ? 'text-red-600' : 'text-green-600'}`}>{formatCurrency(balance)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-5">
                            <p className="text-muted-foreground text-xs">Meters</p>
                            <p className="mt-0.5 text-xl font-bold">{account.meters.length}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-5">
                            <p className="text-muted-foreground text-xs">Outstanding Bills</p>
                            <p className={`mt-0.5 text-xl font-bold ${outstandingCount > 0 ? 'text-orange-600' : ''}`}>{outstandingCount}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-5">
                            <p className="text-muted-foreground text-xs">Total Payments</p>
                            <p className="mt-0.5 text-xl font-bold">{account.payments.length}</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Contact Info */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-semibold">Contact Information</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                        <div className="flex items-start gap-2">
                            <Mail size={14} className="text-muted-foreground mt-0.5 shrink-0" />
                            <span>{account.email || <span className="text-muted-foreground italic">No email</span>}</span>
                        </div>
                        <div className="flex items-start gap-2">
                            <Phone size={14} className="text-muted-foreground mt-0.5 shrink-0" />
                            <span>{account.phone || <span className="text-muted-foreground italic">No phone</span>}</span>
                        </div>
                        <div className="flex items-start gap-2">
                            <MapPin size={14} className="text-muted-foreground mt-0.5 shrink-0" />
                            <span className="whitespace-pre-line">
                                {account.address || <span className="text-muted-foreground italic">No address</span>}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList className="grid w-full max-w-sm grid-cols-3">
                        <TabsTrigger value="meters" className="gap-1.5">
                            <Gauge size={14} /> Meters ({account.meters.length})
                        </TabsTrigger>
                        <TabsTrigger value="bills" className="gap-1.5">
                            <FileText size={14} /> Bills ({account.billings.length})
                        </TabsTrigger>
                        <TabsTrigger value="payments" className="gap-1.5">
                            <CreditCard size={14} /> Payments ({account.payments.length})
                        </TabsTrigger>
                    </TabsList>

                    {/* Meters Tab */}
                    <TabsContent value="meters">
                        <Card>
                            <CardContent className="p-0">
                                {account.meters.length === 0 ? (
                                    <div className="text-muted-foreground py-12 text-center">
                                        <Gauge size={36} className="mx-auto mb-2 opacity-30" />
                                        <p>No meters linked to this account.</p>
                                        <Link href={route('meters.create')} className="mt-3 inline-block">
                                            <Button size="sm">Add Meter</Button>
                                        </Link>
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHead>
                                            <TableRow>
                                                <TableCell>Meter #</TableCell>
                                                <TableCell>Name</TableCell>
                                                <TableCell>Type</TableCell>
                                                <TableCell>Status</TableCell>
                                                <TableCell>#</TableCell>
                                            </TableRow>
                                        </TableHead>
                                        <TableBody>
                                            {account.meters.map((meter) => (
                                                <TableRow key={meter.id}>
                                                    <TableCell className="font-mono text-sm">{meter.meter_number}</TableCell>
                                                    <TableCell>{meter.meter_name}</TableCell>
                                                    <TableCell className="capitalize">{meter.type || '—'}</TableCell>
                                                    <TableCell>
                                                        <Badge className={statusBadge[meter.status] || ''}>{meter.status}</Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Link href={route('meters.show', meter.id)}>
                                                            <Button variant="ghost" size="sm">
                                                                View
                                                            </Button>
                                                        </Link>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Bills Tab */}
                    <TabsContent value="bills">
                        <Card>
                            <CardContent className="p-0">
                                {account.billings.length === 0 ? (
                                    <div className="text-muted-foreground py-12 text-center">
                                        <FileText size={36} className="mx-auto mb-2 opacity-30" />
                                        <p>No bills found for this account.</p>
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHead>
                                            <TableRow>
                                                <TableCell>Period</TableCell>
                                                <TableCell className="text-right">Amount</TableCell>
                                                <TableCell>Status</TableCell>
                                                <TableCell>Issued</TableCell>
                                                <TableCell>Due</TableCell>
                                                <TableCell>#</TableCell>
                                            </TableRow>
                                        </TableHead>
                                        <TableBody>
                                            {account.billings.map((bill) => (
                                                <TableRow key={bill.id}>
                                                    <TableCell className="font-medium">{bill.billing_period}</TableCell>
                                                    <TableCell className="text-right">{formatCurrency(bill.total_amount)}</TableCell>
                                                    <TableCell>
                                                        <Badge className={statusBadge[bill.status] || ''}>{bill.status.replace('_', ' ')}</Badge>
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground text-sm">
                                                        {new Date(bill.issued_at).toLocaleDateString()}
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground text-sm">
                                                        {new Date(bill.due_date).toLocaleDateString()}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Link href={route('billings.show', bill.id)}>
                                                            <Button variant="ghost" size="sm">
                                                                View
                                                            </Button>
                                                        </Link>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Payments Tab */}
                    <TabsContent value="payments">
                        <Card>
                            <CardContent className="p-0">
                                {account.payments.length === 0 ? (
                                    <div className="text-muted-foreground py-12 text-center">
                                        <CreditCard size={36} className="mx-auto mb-2 opacity-30" />
                                        <p>No payments found for this account.</p>
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHead>
                                            <TableRow>
                                                <TableCell>Date</TableCell>
                                                <TableCell className="text-right">Amount</TableCell>
                                                <TableCell>Method</TableCell>
                                                <TableCell>Reference</TableCell>
                                                <TableCell>Status</TableCell>
                                                <TableCell>#</TableCell>
                                            </TableRow>
                                        </TableHead>
                                        <TableBody>
                                            {account.payments.map((payment) => (
                                                <TableRow key={payment.id}>
                                                    <TableCell className="text-sm">{new Date(payment.payment_date).toLocaleDateString()}</TableCell>
                                                    <TableCell className="text-right font-medium">{formatCurrency(payment.amount)}</TableCell>
                                                    <TableCell className="capitalize">{payment.method}</TableCell>
                                                    <TableCell className="text-muted-foreground font-mono text-xs">
                                                        {payment.reference || '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge className={statusBadge[payment.status] || ''}>{payment.status}</Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Link href={route('payments.show', payment.id)}>
                                                            <Button variant="ghost" size="sm">
                                                                View
                                                            </Button>
                                                        </Link>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
