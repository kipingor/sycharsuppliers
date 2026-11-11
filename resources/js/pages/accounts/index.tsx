import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { Badge } from '@/components/ui/badge';
import { PlusCircle, Users, Eye, Pencil, Trash } from 'lucide-react';
import { debounce } from 'lodash';
import Pagination from '@/components/pagination';
import { formatCurrency } from '@/lib/utils';
import type { BreadcrumbItem, SharedData } from '@/types';

interface Account {
    id: number;
    account_number: string;
    name: string;
    email?: string;
    phone?: string;
    status: 'active' | 'suspended' | 'inactive';
    billings_count: number;
    payments_count: number;
    meters?: Array<{ id: number; meter_number: string; status: string }>;
}

interface AccountsPageProps {
    accounts: {
        data: Account[];
        links: any[];
    };
    filters: {
        search?: string;
        status?: string;
    };
    can: {
        create: boolean;
        generateFromResidents: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounts', href: '/accounts' },
];

export default function AccountsIndex() {
    const { accounts, filters, can } = usePage<SharedData & AccountsPageProps>().props;
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = useMemo(
        () =>
            debounce((query: string) => {
                router.get(route('accounts.index'), { ...filters, search: query || undefined }, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                });
            }, 300),
        [filters],
    );

    useEffect(() => () => handleSearch.cancel(), [handleSearch]);

    const handleGenerateAccounts = () => {
        if (confirm('Generate accounts from all residents without accounts?')) {
            router.post(route('accounts.generate-from-residents'), {}, {
                preserveScroll: true,
            });
        }
    };

    const statusClasses = {
        active: 'bg-green-400/20 text-green-700 dark:bg-green-400/10 dark:text-green-300',
        suspended: 'bg-yellow-400/20 text-yellow-700 dark:bg-yellow-400/10 dark:text-yellow-300',
        inactive: 'bg-gray-400/20 text-gray-700 dark:bg-gray-400/10 dark:text-gray-300',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Accounts" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-2xl font-bold">Accounts</h1>
                    <div className="flex min-w-0 flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Input
                            type="text"
                            placeholder="Search accounts..."
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                handleSearch(e.target.value);
                            }}
                            className="w-full sm:w-64"
                        />
                        {can.generateFromResidents && (
                            <Button
                                variant="outline"
                                onClick={handleGenerateAccounts}
                                className="flex w-full items-center gap-2 sm:w-auto"
                            >
                                <Users size={18} /> Generate from Residents
                            </Button>
                        )}
                        {can.create && (
                            <Link href={route('accounts.create')}>
                                <Button className="flex w-full items-center gap-2 sm:w-auto">
                                    <PlusCircle size={18} /> Create Account
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {accounts.data.length === 0 ? (
                    <div className="text-center py-12">
                        <p className="text-gray-500 dark:text-gray-400">No accounts found.</p>
                        {can.create && (
                            <div className="mt-4 flex gap-2 justify-center">
                                <Link href={route('accounts.create')}>
                                    <Button>Create First Account</Button>
                                </Link>
                                {can.generateFromResidents && (
                                    <Button variant="outline" onClick={handleGenerateAccounts}>
                                        Generate from Residents
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="hidden md:block">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {['Account #', 'Name', 'Contact', 'Meters', 'Bills', 'Payments', 'Status', 'Actions'].map((header) => (
                                        <TableHead key={header} className="font-medium">
                                            {header}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {accounts.data.map((account) => (
                                    <TableRow key={account.id}>
                                        <TableCell className="font-mono text-xs">
                                            {account.account_number}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {account.name}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {account.email && <div>{account.email}</div>}
                                            {account.phone && <div>{account.phone}</div>}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {account.meters?.length || 0}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {account.billings_count}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {account.payments_count}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge className={statusClasses[account.status]}>
                                                {account.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-1">
                                                <Link href={route('accounts.show', account.id)}>
                                                    <Button variant="ghost" size="sm">
                                                        <Eye size={16} />
                                                    </Button>
                                                </Link>
                                                <Link href={route('accounts.edit', account.id)}>
                                                    <Button variant="ghost" size="sm">
                                                        <Pencil size={16} />
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        if (confirm(`Delete account "${account.name}"?`)) {
                                                            router.delete(route('accounts.destroy', account.id), { preserveScroll: true });
                                                        }
                                                    }}
                                                >
                                                    <Trash size={16} />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {accounts.data.map((account) => (
                                <div key={account.id} className="rounded-lg border bg-card p-4 shadow-sm">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <Link href={route('accounts.show', account.id)} className="font-semibold hover:underline">
                                                {account.name}
                                            </Link>
                                            <p className="mt-1 break-all font-mono text-xs text-muted-foreground">{account.account_number}</p>
                                        </div>
                                        <Badge className={statusClasses[account.status]}>{account.status}</Badge>
                                    </div>
                                    <div className="mt-3 space-y-1 text-sm">
                                        {account.email && <p className="break-all text-muted-foreground">{account.email}</p>}
                                        {account.phone && <p className="text-muted-foreground">{account.phone}</p>}
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Badge variant="outline">{account.meters?.length || 0} meters</Badge>
                                        <Badge variant="outline">{account.billings_count} bills</Badge>
                                        <Badge variant="outline">{account.payments_count} payments</Badge>
                                    </div>
                                    <div className="mt-3 flex justify-end gap-1 border-t pt-2">
                                        <Link href={route('accounts.show', account.id)}>
                                            <Button variant="ghost" size="sm" aria-label={`View ${account.name}`}><Eye size={16} /></Button>
                                        </Link>
                                        <Link href={route('accounts.edit', account.id)}>
                                            <Button variant="ghost" size="sm" aria-label={`Edit ${account.name}`}><Pencil size={16} /></Button>
                                        </Link>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive hover:text-destructive"
                                            aria-label={`Delete ${account.name}`}
                                            onClick={() => {
                                                if (confirm(`Delete account "${account.name}"?`)) {
                                                    router.delete(route('accounts.destroy', account.id), { preserveScroll: true });
                                                }
                                            }}
                                        >
                                            <Trash size={16} />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <Pagination links={accounts.links} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}