import bill from '@/components/bill';
import Pagination from '@/components/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { debounce } from 'lodash';
import { EllipsisVertical, Eye, Pencil, PlusCircle, Trash } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Account {
    id: number;
    name: string;
    account_number?: string;
}

interface Payment {
    id: number;
    account_id: number;
    account: Account;
    payment_date: string;
    amount: number;
    method: string;
    transaction_id?: string;
    reference?: string;
    status: string;
    reconciliation_status?: string;
}

interface PaymentsPageProps {
    payments: {
        data: Payment[];
        links: any[];
    };
    filters: {
        search?: string;
        status?: string;
        reconciliation_status?: string;
        method?: string;
        account_id?: number;
    };
    accounts: Account[];
    can: {
        create: boolean;
        update?: boolean;
        delete?: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payments',
        href: route('payments.index'),
    },
];

export default function Payments() {
    const { payments, filters, accounts, can, auth } = usePage<SharedData & PaymentsPageProps>().props;
    const [search, setSearch] = useState<string>(filters.search || '');

    const handleSearch = debounce((query: string) => {
        router.get(
            route('payments.index'),
            { search: query },
            {
                only: ['payments'],
                replace: true,
                preserveState: true,
            },
        );
    }, 300);

    const statusClasses: Record<string, string> = {
        pending: 'bg-yellow-400/20 text-yellow-700 dark:bg-yellow-400/10 dark:text-yellow-300',
        completed: 'bg-green-400/20 text-green-700 dark:bg-green-400/10 dark:text-green-300',
        failed: 'bg-red-400/20 text-red-700 dark:bg-red-400/10 dark:text-red-300',
        cancelled: 'bg-gray-400/20 text-gray-700 dark:bg-gray-400/10 dark:text-gray-300',
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this payment? This action cannot be undone.')) {
            router.delete(route('payments.destroy', id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payments" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Payments</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search payments..."
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                handleSearch(e.target.value);
                            }}
                            className="w-64"
                        />
                        {can.create && (
                            <Link href={route('payments.create')}>
                                <Button className="flex items-center gap-2">
                                    <PlusCircle size={18} /> Record Payment
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {payments.data.length === 0 ? (
                    <div className="py-12 text-center">
                        <p className="text-gray-500 dark:text-gray-400">No payments found.</p>
                        {can.create && (
                            <Link href={route('payments.create')}>
                                <Button className="mt-4">Record Your First Payment</Button>
                            </Link>
                        )}
                    </div>
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {['ID', 'Account', 'Date', 'Amount', 'Method', 'Reference', 'Status', 'Actions'].map((header) => (
                                        <TableHead key={header} className="text-left font-medium">
                                            {header}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payments.data.map((payment) => (
                                    <TableRow key={payment.id}>
                                        <TableCell className="text-xs">{payment.id}</TableCell>
                                        <TableCell className="text-xs">
                                            <div>
                                                <p className="font-medium">{payment.account.name}</p>
                                                {payment.account.account_number && (
                                                    <p className="text-xs text-gray-500">{payment.account.account_number}</p>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-xs">{new Date(payment.payment_date).toLocaleDateString()}</TableCell>
                                        <TableCell className="text-xs font-medium">{formatCurrency(Number(payment.amount))}</TableCell>
                                        <TableCell className="text-xs capitalize">{payment.method}</TableCell>
                                        <TableCell className="text-xs">{payment.reference || payment.transaction_id || 'N/A'}</TableCell>
                                        <TableCell className="text-xs">
                                            <Badge className={statusClasses[payment.status.toLowerCase()] || statusClasses.pending}>
                                                {payment.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="sm">
                                                        <EllipsisVertical size={16} />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route('payments.show', payment.id)}>
                                                            <Eye size={16} className="mr-2" /> View Details
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    {/* Check if payment can be edited based on status */}
                                                    {payment.reconciliation_status === 'pending' && (
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('payments.edit', payment.id)}>
                                                                <Pencil size={16} className="mr-2" /> Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    )}

                                                    {/* Check if payment can be deleted based on status */}
                                                    {payment.reconciliation_status === 'pending' && (payment as any).allocations_count === 0 && (
                                                        <DropdownMenuItem onClick={() => handleDelete(payment.id)} className="text-red-600">
                                                            <Trash size={16} className="mr-2" /> Delete
                                                        </DropdownMenuItem>
                                                    )}

                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <Pagination links={payments.links} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
