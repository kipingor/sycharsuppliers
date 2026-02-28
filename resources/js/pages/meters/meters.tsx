import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Account, type Meter } from '@/types/models';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Paginated<T> {
    data: T[];
    links: any[];
}

import Pagination from '@/components/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { debounce } from 'lodash';
import { EllipsisVertical, Eye, Pencil, PlusCircle, Trash } from 'lucide-react';
import { toast } from 'sonner';

interface MeterProps {
    meters: {
        data: Meter[];
        links: any[];
    };
    filters: {
        search?: string;
        status?: string;
        type?: string;
        meter_type?: string;
        account_id?: number;
        bulk_only?: boolean;
        individual_only?: boolean;
    };
    accounts: Account[];
    can: {
        create: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Meters',
        href: route('meters.index'),
    },
];

export default function Meters() {
    const { meters, filters, can } = usePage<{
        meters: Paginated<Meter>;
        filters: {
            search?: string;
        };
        can: Record<string, boolean>;
    }>().props;

    const search = filters.search ?? '';

    const handleSearch = debounce((value: string) => {
        router.get(
            route('meters.index'),
            { search: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 300);

    const handleDelete = (id: number): void => {
        if (confirm('Are you sure you want to delete this meter?')) {
            router.delete(route('meters.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    // Success handled by Inertia
                    toast('Meter deleted successfully!');
                },
                onError: () => {
                    toast('Failed to delete meter. Please try again.');
                    // alert('Failed to delete meter');
                },
            });
        }
    };

    const statusClasses = {
        active: 'bg-lime-400/20 text-lime-700 dark:bg-lime-400/10 dark:text-lime-300',
        replaced: 'bg-amber-400/15 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
        inactive: 'bg-stone-400/15 text-stone-700 dark:bg-stone-400/10 dark:text-stone-400',
        faulty: 'bg-red-400/15 text-red-700 dark:bg-red-400/10 dark:text-red-400',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Meters" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Meters</h1>
                    <div className="flex gap-2">
                        <Input
                            value={search}
                            placeholder="Search by meter number or resident name..."
                            onChange={(e) => handleSearch(e.target.value)}
                        />
                        {can.create && (
                            <Link href={route('meters.create')}>
                                <Button className="flex items-center gap-2">
                                    <PlusCircle size={18} /> Add Meter
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {meters.data.length === 0 ? (
                    <div className="py-12 text-center">
                        <p className="text-gray-500 dark:text-gray-400">No meters found.</p>
                        {can.create && (
                            <Link href={route('meters.create')}>
                                <Button className="flex items-center gap-2 mt-4">
                                    <PlusCircle size={18} /> Add Your First Meter
                                </Button>
                            </Link>
                        )}
                    </div>
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {['ID', 'Meter Number', 'Meter Name', 'Account', 'Status', 'Actions'].map((header) => (
                                        <TableHead key={header} className="text-left text-xs font-medium">
                                            {header}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {meters.data.map((meter) => (
                                    <TableRow key={meter.id}>
                                        <TableCell className="text-xs">{meter.id}</TableCell>
                                        <TableCell className="text-xs">{meter.meter_number}</TableCell>
                                        <TableCell className="text-xs font-medium">{meter.meter_name}</TableCell>
                                        <TableCell className="text-xs">
                                            {meter.account?.name || 'N/A'}
                                            {meter.account?.account_number && (
                                                <span className="ml-1 text-gray-500">({meter.account.account_number})</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            <span
                                                className={`inline-flex items-center gap-x-1.5 rounded-md px-1.5 py-0.5 text-sm/5 font-medium sm:text-xs/5 ${statusClasses[meter.status ?? 'active'] || statusClasses.active}`}
                                            >
                                                {meter.status ?? 'active'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="place-items-center text-xs">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger>
                                                    <EllipsisVertical size={16} />
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route('meters.show', meter.id)}>
                                                            <Eye size={16} className="mr-2" /> View
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route('meters.edit', meter.id)}>
                                                            <Pencil size={16} className="mr-2" /> Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem className="text-xs" onClick={() => handleDelete(meter.id)}>
                                                        <Trash size={16} className="mr-2" /> Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {/* Pagination */}
                        <Pagination links={meters.links} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
