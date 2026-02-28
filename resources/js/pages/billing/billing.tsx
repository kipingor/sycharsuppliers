import Pagination from '@/components/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { debounce } from 'lodash';
import { EllipsisVertical, Eye, PlusCircle } from 'lucide-react';
import { useState } from 'react';

interface Account {
    id: number;
    name: string;
    account_number?: string;
}

interface Bill {
    id: number;
    account_id: number;
    billing_period: string;
    total_amount: number;
    status: 'pending' | 'partially_paid' | 'paid' | 'overdue' | 'voided';
    issued_at: string;
    due_date: string;
    account?: Account;
}

interface BillsPageProps {
    bills: {
        data: Bill[];
        links: any[];
    };
    filters: {
        search?: string;
        status?: string;
        period?: string;
        account_id?: number;
        from_date?: string;
        to_date?: string;
    };
    accounts: Account[];
    availablePeriods: string[];
    can: {
        create: boolean;
        generate: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Bills',
        href: route('billings.index'),
    },
];

export default function Bills() {
    const { bills, filters, accounts, availablePeriods, can, auth } = usePage<SharedData & BillsPageProps>().props;

    const [search, setSearch] = useState<string>(filters.search || '');

    const [isLoading, setIsLoading] = useState(false);

    const handleSearch = debounce((query: string) => {
        setIsLoading(true);
        router.get(
            route('billings.index'),
            { search: query },
            {
                only: ['bills'],
                replace: true,
                preserveState: true,
                onFinish: () => setIsLoading(false),
            },
        );
    }, 300);

    {isLoading && <div>Loading...</div>}

    const statusClasses = {
        paid: 'bg-lime-400/20 text-lime-700 dark:bg-lime-400/10 dark:text-lime-300',
        pending: 'bg-sky-400/15 text-sky-700 dark:bg-sky-400/10 dark:text-sky-400',
        overdue: 'bg-pink-400/15 text-pink-700 dark:bg-pink-400/10 dark:text-pink-400',
        partially_paid: 'bg-blue-400/15 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400',
        voided: 'bg-gray-400/15 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400',
        default: 'bg-stone-400/15 text-stone-700 dark:bg-stone-400/10 dark:text-stone-400',
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this bill?')) {
            router.delete(route('billings.destroy', id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bills" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Bills</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search bills..."
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                handleSearch(e.target.value);
                            }}
                            className="w-64"
                        />
                        {can.generate && (
                            <Link href={route('billings.create')}>
                                <Button className="flex items-center gap-2">
                                    <PlusCircle size={18} /> Generate Bill
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {bills.data.length === 0 ? (
                    <div className="py-12 text-center">
                        <p className="text-gray-500 dark:text-gray-400">No bills found.</p>
                        {can.generate && (
                            <Link href={route('billings.create')}>
                                <Button className="mt-4">Generate Your First Bill</Button>
                            </Link>
                        )}
                    </div>
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {['ID', 'Account', 'Period', 'Amount', 'Status', 'Actions'].map((header) => (
                                        <TableHead key={header} className="text-left font-medium">
                                            {header}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {bills.data.map((bill) => (
                                    <TableRow key={bill.id}>
                                        <TableCell className="text-xs">{bill.id}</TableCell>
                                        <TableCell className="text-xs">
                                            {bill.account?.name || 'N/A'}
                                            {bill.account?.account_number && (
                                                <span className="ml-1 text-gray-500">({bill.account.account_number})</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs">{bill.billing_period}</TableCell>
                                        <TableCell className="text-xs">{formatCurrency(Number(bill.total_amount))}</TableCell>
                                        <TableCell className="text-xs">
                                            <span
                                                className={`inline-flex items-center gap-x-1.5 rounded-md px-1.5 py-0.5 text-sm/5 font-medium sm:text-xs/5 ${statusClasses[bill.status] || statusClasses.default}`}
                                            >
                                                {bill.status.replace('_', ' ')}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger>
                                                    <EllipsisVertical size={16} />
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route('billings.show', bill.id)}>
                                                            <Eye size={16} className="mr-2" /> View Details
                                                        </Link>
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {/* Pagination */}
                        <Pagination links={bills.links} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
