import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
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

    const handleSearch = debounce((query: string) => {
        router.get(route('accounts.index'), { search: query }, {
            preserveState: true,
            replace: true,
        });
    }, 300);

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
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex justify-between items-center mb-4">
                    <h1 className="text-2xl font-bold">Accounts</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search accounts..."
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                handleSearch(e.target.value);
                            }}
                            className="w-64"
                        />
                        {can.generateFromResidents && (
                            <Button
                                variant="outline"
                                onClick={handleGenerateAccounts}
                                className="flex items-center gap-2"
                            >
                                <Users size={18} /> Generate from Residents
                            </Button>
                        )}
                        {can.create && (
                            <Link href={route('accounts.create')}>
                                <Button className="flex items-center gap-2">
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
                        <Table>
                            <TableHead>
                                <TableRow>
                                    {['Account #', 'Name', 'Contact', 'Meters', 'Bills', 'Payments', 'Status', 'Actions'].map((header) => (
                                        <TableCell key={header} className="font-medium">
                                            {header}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            </TableHead>
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
                                            <div className="flex gap-2">
                                                <Link href={route('accounts.show', account.id)}>
                                                    <Button variant="ghost" size="sm">
                                                        <Eye size={16} />
                                                    </Button>
                                                </Link>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <Pagination links={accounts.links} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}