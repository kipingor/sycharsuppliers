import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Pagination from '@/components/pagination';
import { formatCurrency } from '@/lib/utils';
import { EllipsisVertical, Eye, Pencil, Trash, PlusCircle, CheckCircle, Clock, TrendingDown, Wallet } from 'lucide-react';
import { debounce } from 'lodash';
import { useState } from 'react';

interface Expense {
    id: number; category: string; description: string; amount: number;
    expense_date: string; receipt_number?: string; status: boolean;
    approved_by?: number; rejected_by?: number;
    approver?: { id: number; name: string };
}

interface ExpensesIndexProps {
    expenses: { data: Expense[]; links: any[] };
    summary: { total: number; approved: number; pending: number };
    filters: { search?: string; status?: string; category?: string; from_date?: string; to_date?: string };
    categories: string[];
    can: { create: boolean };
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Expenses', href: route('expenses.index') }];

function statusBadge(e: Expense) {
    if (e.status && e.approved_by) return <Badge className="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">Approved</Badge>;
    if (!e.status && e.rejected_by) return <Badge className="bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">Rejected</Badge>;
    return <Badge className="bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300">Pending</Badge>;
}

export default function ExpensesIndex() {
    const { expenses, summary, filters, categories, can } = usePage<SharedData & ExpensesIndexProps>().props;
    const [search, setSearch] = useState(filters.search || '');

    const applyFilter = (key: string, value: string) =>
        router.get(route('expenses.index'), { ...filters, [key]: value || undefined }, {
            preserveState: true, replace: true, only: ['expenses', 'summary'],
        });

    const handleSearch = debounce((q: string) => applyFilter('search', q), 300);

    const handleDelete = (id: number) => {
        if (confirm('Delete this expense? This cannot be undone.'))
            router.delete(route('expenses.destroy', id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Expenses" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Expenses</h1>
                    <div className="flex gap-2">
                        <Link href={route('expenses.budgets')}>
                            <Button variant="outline" size="sm"><Wallet size={16} className="mr-2" />Budgets</Button>
                        </Link>
                        {can.create && (
                            <Link href={route('expenses.create')}>
                                <Button size="sm"><PlusCircle size={16} className="mr-2" />Add Expense</Button>
                            </Link>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {[
                        { label: 'Total (filtered)', value: formatCurrency(summary.total), icon: TrendingDown, color: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-950/30' },
                        { label: 'Approved', value: formatCurrency(summary.approved), icon: CheckCircle, color: 'text-green-600', bg: 'bg-green-50 dark:bg-green-950/30' },
                        { label: 'Awaiting Approval', value: `${summary.pending} expense${summary.pending !== 1 ? 's' : ''}`, icon: Clock, color: 'text-yellow-600', bg: 'bg-yellow-50 dark:bg-yellow-950/30' },
                    ].map((card) => (
                        <Card key={card.label}>
                            <CardContent className="pt-5 flex items-center gap-3">
                                <div className={`w-10 h-10 rounded-lg ${card.bg} flex items-center justify-center shrink-0`}>
                                    <card.icon size={20} className={card.color} />
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">{card.label}</p>
                                    <p className="text-lg font-semibold">{card.value}</p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="flex flex-wrap gap-2">
                    <Input placeholder="Search..." value={search}
                        onChange={(e) => { setSearch(e.target.value); handleSearch(e.target.value); }}
                        className="w-56" />
                    {(['status','category'] as const).map((key) => (
                        key === 'status' ? (
                            <select key={key} value={filters.status || ''} onChange={(e) => applyFilter('status', e.target.value)}
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="">All statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        ) : (
                            <select key={key} value={filters.category || ''} onChange={(e) => applyFilter('category', e.target.value)}
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="">All categories</option>
                                {categories.map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        )
                    ))}
                    <Input type="date" value={filters.from_date || ''} onChange={(e) => applyFilter('from_date', e.target.value)} className="w-40" />
                    <Input type="date" value={filters.to_date || ''} onChange={(e) => applyFilter('to_date', e.target.value)} className="w-40" />
                </div>

                {expenses.data.length === 0 ? (
                    <div className="py-16 text-center text-muted-foreground">
                        <TrendingDown size={40} className="mx-auto mb-3 opacity-30" />
                        <p>No expenses found.</p>
                        {can.create && <Link href={route('expenses.create')}><Button className="mt-4" size="sm">Add First Expense</Button></Link>}
                    </div>
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {['Date','Category','Description','Amount','Receipt #','Status','Actions'].map((h) => (
                                        <TableHead key={h}>{h}</TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {expenses.data.map((expense) => (
                                    <TableRow key={expense.id}>
                                        <TableCell className="text-xs">{new Date(expense.expense_date).toLocaleDateString()}</TableCell>
                                        <TableCell className="text-xs"><Badge variant="outline">{expense.category}</Badge></TableCell>
                                        <TableCell className="text-xs max-w-[200px] truncate">{expense.description}</TableCell>
                                        <TableCell className="text-xs font-medium">{formatCurrency(Number(expense.amount))}</TableCell>
                                        <TableCell className="text-xs text-muted-foreground">{expense.receipt_number || '—'}</TableCell>
                                        <TableCell>{statusBadge(expense)}</TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="sm"><EllipsisVertical size={16} /></Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route('expenses.show', expense.id)}><Eye size={14} className="mr-2" />View</Link>
                                                    </DropdownMenuItem>
                                                    {!expense.status && !expense.rejected_by && (
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('expenses.edit', expense.id)}><Pencil size={14} className="mr-2" />Edit</Link>
                                                        </DropdownMenuItem>
                                                    )}
                                                    {!expense.status && (
                                                        <DropdownMenuItem onClick={() => handleDelete(expense.id)} className="text-red-600">
                                                            <Trash size={14} className="mr-2" />Delete
                                                        </DropdownMenuItem>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <Pagination links={expenses.links} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}