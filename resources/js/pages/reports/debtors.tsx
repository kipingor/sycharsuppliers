import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { formatCurrency } from '@/lib/utils';
import { Users, Phone, Mail } from 'lucide-react';

interface Debtor {
    account_id: number; account_number: string; name: string;
    phone?: string; email?: string; address?: string;
    outstanding_balance: number; bill_count: number;
    last_payment_date?: string; last_payment_amount?: number;
    days_overdue: number; status: string;
}

interface DebtorsProps {
    debtors: Debtor[]; grand_total: number; total_count: number;
    min_balance: number; generated_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: route('reports.index') },
    { title: 'Debtors Report', href: '#' },
];

export default function DebtorsReport() {
    const { debtors, grand_total, total_count, min_balance, generated_at } = usePage<SharedData & DebtorsProps>().props;

    const applyFilter = (minBal: string) =>
        router.get(route('reports.debtors'), { min_balance: minBal }, { preserveState: true });

    const severityBadge = (days: number) => {
        if (days === 0) return <Badge className="bg-green-100 text-green-700 dark:bg-green-900/30 text-xs">Current</Badge>;
        if (days <= 30) return <Badge className="bg-yellow-100 text-yellow-700 text-xs">{days}d overdue</Badge>;
        if (days <= 60) return <Badge className="bg-orange-100 text-orange-700 text-xs">{days}d overdue</Badge>;
        return <Badge className="bg-red-100 text-red-700 text-xs">{days}d overdue</Badge>;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Debtors Report" />
            <div className="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">
                <div className="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2"><Users size={22} className="text-purple-600" />Debtors Report</h1>
                        <p className="text-sm text-muted-foreground">Generated {generated_at} · {total_count} account{total_count !== 1 ? 's' : ''} · Total owed: {formatCurrency(grand_total)}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">Min balance:</span>
                        <Input type="number" min="0" step="100" defaultValue={min_balance}
                            onBlur={(e) => applyFilter(e.target.value)} className="w-32" placeholder="0" />
                    </div>
                </div>

                <Card>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Account</TableHead>
                                        <TableHead>Contact</TableHead>
                                        <TableHead className="text-right">Outstanding</TableHead>
                                        <TableHead className="text-right">Bills</TableHead>
                                        <TableHead>Last Payment</TableHead>
                                        <TableHead>Age</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {debtors.length === 0 ? (
                                        <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground py-10">No debtors found.</TableCell></TableRow>
                                    ) : debtors.map((debtor) => (
                                        <TableRow key={debtor.account_id}>
                                            <TableCell>
                                                <Link href={route('accounts.show', debtor.account_id)} className="hover:underline">
                                                    <p className="font-medium text-sm">{debtor.name}</p>
                                                    <p className="text-xs text-muted-foreground">{debtor.account_number}</p>
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <div className="space-y-1">
                                                    {debtor.phone && (
                                                        <p className="text-xs flex items-center gap-1">
                                                            <Phone size={10} className="text-muted-foreground" />{debtor.phone}
                                                        </p>
                                                    )}
                                                    {debtor.email && (
                                                        <p className="text-xs flex items-center gap-1">
                                                            <Mail size={10} className="text-muted-foreground" />{debtor.email}
                                                        </p>
                                                    )}
                                                    {!debtor.phone && !debtor.email && <span className="text-xs text-muted-foreground">—</span>}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right font-semibold">{formatCurrency(debtor.outstanding_balance)}</TableCell>
                                            <TableCell className="text-right text-sm">{debtor.bill_count}</TableCell>
                                            <TableCell className="text-xs">
                                                {debtor.last_payment_date ? (
                                                    <>
                                                        <p>{new Date(debtor.last_payment_date).toLocaleDateString()}</p>
                                                        {debtor.last_payment_amount && (
                                                            <p className="text-muted-foreground">{formatCurrency(debtor.last_payment_amount)}</p>
                                                        )}
                                                    </>
                                                ) : <span className="text-muted-foreground">Never paid</span>}
                                            </TableCell>
                                            <TableCell>{severityBadge(debtor.days_overdue)}</TableCell>
                                            <TableCell>
                                                <Badge variant={debtor.status === 'active' ? 'outline' : 'secondary'} className="text-xs capitalize">
                                                    {debtor.status}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}