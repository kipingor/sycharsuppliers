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
import { EllipsisVertical, Eye, PlusCircle, Download } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

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
    status: 'pending' | 'partially_paid' | 'paid' | 'overdue' | 'voided' | 'written_off';
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
    const [period, setPeriod] = useState<string>(filters.period || '');

    const [isLoading, setIsLoading] = useState(false);

    const handleSearch = useMemo(
        () =>
            debounce((query: string, billingPeriod: string) => {
                setIsLoading(true);
                router.get(
                    route('billings.index'),
                    { search: query || undefined, period: billingPeriod || undefined },
                    {
                        only: ['bills', 'filters'],
                        replace: true,
                        preserveState: true,
                        onFinish: () => setIsLoading(false),
                    },
                );
            }, 300),
        [],
    );

    useEffect(() => () => handleSearch.cancel(), [handleSearch]);

    const handlePeriodChange = (value: string) => {
        const nextPeriod = value === 'all' ? '' : value;
        setPeriod(nextPeriod);
        handleSearch.cancel();
        handleSearch(search, nextPeriod);
    };

    const handleDownloadPeriod = () => {
        if (!period) {
            return;
        }
        window.location.href = route('billings.period.download', { period });
    };

    {isLoading && <div>Loading...</div>}

    const statusClasses = {
        paid: 'bg-lime-400/20 text-lime-700 dark:bg-lime-400/10 dark:text-lime-300',
        pending: 'bg-sky-400/15 text-sky-700 dark:bg-sky-400/10 dark:text-sky-400',
        overdue: 'bg-pink-400/15 text-pink-700 dark:bg-pink-400/10 dark:text-pink-400',
        partially_paid: 'bg-blue-400/15 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400',
        written_off: 'bg-amber-400/15 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
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
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-2xl font-bold">Bills</h1>
                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                        <Input
                            type="text"
                            placeholder="Search bills..."
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                handleSearch(e.target.value, period);
                            }}
                            className="w-full sm:w-64"
                        />
                        <Select value={period || 'all'} onValueChange={handlePeriodChange}>
                            <SelectTrigger className="w-full sm:w-[180px]">
                                <SelectValue placeholder="Billing period" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All periods</SelectItem>
                                {availablePeriods.map((availablePeriod) => (
                                    <SelectItem key={availablePeriod} value={availablePeriod}>
                                        {availablePeriod}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button variant="outline" onClick={handleDownloadPeriod} disabled={!period} className="flex items-center gap-2">
                            <Download size={16} /> Download cycle
                        </Button>
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
                        <div className="hidden md:block">
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
                        </div>

                        <div className="space-y-3 md:hidden">
                            {bills.data.map((bill) => (
                                <div key={bill.id} className="rounded-lg border bg-card p-4 shadow-sm">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <Link href={route('billings.show', bill.id)} className="font-semibold hover:underline">
                                                {bill.account?.name || 'N/A'}
                                            </Link>
                                            {bill.account?.account_number && (
                                                <p className="mt-1 text-sm text-muted-foreground">{bill.account.account_number}</p>
                                            )}
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" aria-label={`Actions for bill ${bill.id}`}>
                                                    <EllipsisVertical size={18} />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link href={route('billings.show', bill.id)}><Eye size={16} className="mr-2" /> View Details</Link>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <div>
                                            <p className="text-muted-foreground">{bill.billing_period}</p>
                                            <p className="mt-1 font-semibold">{formatCurrency(Number(bill.total_amount))}</p>
                                        </div>
                                        <span className={`inline-flex rounded-md px-2 py-1 text-xs font-medium ${statusClasses[bill.status] || statusClasses.default}`}>
                                            {bill.status.replace('_', ' ')}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Pagination */}
                        <Pagination links={bills.links} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
