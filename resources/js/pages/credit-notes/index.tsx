import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { formatCurrency } from '@/lib/utils';
import { FileMinus, Search } from 'lucide-react';
import { useState } from 'react';

interface CreditNote {
    id: number;
    reference: string;
    type: string;
    amount: number;
    reason: string;
    status: 'applied' | 'voided';
    created_at: string;
    billing?: {
        id: number;
        billing_period: string;
        account?: { name: string; account_number?: string };
    };
    previous_account?: { name: string; account_number?: string };
    created_by?: { name: string };
}

interface PaginatedNotes {
    data: CreditNote[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    notes: PaginatedNotes;
    filters: { search?: string; status?: string };
}

const TYPE_LABELS: Record<string, string> = {
    previous_resident_debt: 'Prev. Resident Debt',
    billing_error:          'Billing Error',
    goodwill:               'Goodwill',
    other:                  'Other',
};

const TYPE_CLASSES: Record<string, string> = {
    previous_resident_debt: 'bg-amber-400/15 text-amber-700 dark:text-amber-400',
    billing_error:          'bg-red-400/15 text-red-700 dark:text-red-400',
    goodwill:               'bg-purple-400/15 text-purple-700 dark:text-purple-400',
    other:                  'bg-gray-400/15 text-gray-600',
};

export default function CreditNotesIndex() {
    const { notes, filters } = usePage<SharedData & Props>().props;

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Credit Notes', href: '#' },
    ];

    const applyFilters = () => {
        router.get(route('credit-notes.index'), { search, status }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Credit Notes" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold flex items-center gap-2">
                        <FileMinus size={22} className="text-amber-500" />
                        Credit Notes
                    </h1>
                    <p className="text-sm text-muted-foreground">{notes.total} total</p>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="pt-4 pb-4">
                        <div className="flex flex-wrap gap-3 items-end">
                            <div className="flex-1 min-w-[200px]">
                                <div className="relative">
                                    <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={e => setSearch(e.target.value)}
                                        onKeyDown={e => e.key === 'Enter' && applyFilters()}
                                        placeholder="Search reference, account, reason…"
                                        className="w-full pl-9 pr-3 py-2 border rounded-md text-sm bg-background"
                                    />
                                </div>
                            </div>
                            <select
                                value={status}
                                onChange={e => setStatus(e.target.value)}
                                className="border rounded-md px-3 py-2 text-sm bg-background"
                            >
                                <option value="">All statuses</option>
                                <option value="applied">Applied</option>
                                <option value="voided">Voided</option>
                            </select>
                            <Button size="sm" onClick={applyFilters}>Filter</Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Table */}
                <Card>
                    <CardContent className="pt-0 pb-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Bill</TableHead>
                                    <TableHead>Account</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Previous Resident</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {notes.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={8} className="text-center text-muted-foreground py-12">
                                            No credit notes found.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    notes.data.map(note => (
                                        <TableRow key={note.id} className={note.status === 'voided' ? 'opacity-50' : ''}>
                                            <TableCell className="font-mono text-sm">{note.reference}</TableCell>
                                            <TableCell>
                                                {note.billing ? (
                                                    <Link
                                                        href={route('billings.show', note.billing.id)}
                                                        className="text-blue-600 hover:underline text-sm"
                                                    >
                                                        Bill #{note.billing.id}
                                                    </Link>
                                                ) : '—'}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {note.billing?.account?.name ?? '—'}
                                                {note.billing?.account?.account_number && (
                                                    <span className="text-muted-foreground ml-1 text-xs">
                                                        ({note.billing.account.account_number})
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge className={TYPE_CLASSES[note.type] ?? 'bg-gray-100'}>
                                                    {TYPE_LABELS[note.type] ?? note.type}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {note.previous_account
                                                    ? note.previous_account.name
                                                    : <span className="text-muted-foreground">—</span>}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {new Date(note.created_at).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <Badge className={
                                                    note.status === 'applied'
                                                        ? 'bg-lime-400/20 text-lime-700 dark:text-lime-300'
                                                        : 'bg-gray-400/15 text-gray-600'
                                                }>
                                                    {note.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right font-medium text-amber-600">
                                                − {formatCurrency(note.amount)}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Pagination */}
                {notes.last_page > 1 && (
                    <div className="flex gap-1 justify-center flex-wrap">
                        {notes.links.map((link, i) => (
                            <button
                                key={i}
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`px-3 py-1.5 rounded text-sm border ${
                                    link.active
                                        ? 'bg-primary text-primary-foreground border-primary'
                                        : link.url
                                            ? 'hover:bg-muted border-border'
                                            : 'opacity-40 cursor-not-allowed border-border'
                                }`}
                            />
                        ))}
                    </div>
                )}

            </div>
        </AppLayout>
    );
}