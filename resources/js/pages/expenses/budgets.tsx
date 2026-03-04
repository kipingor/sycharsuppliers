import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { formatCurrency } from '@/lib/utils';
import { Trash, PlusCircle, AlertTriangle, CheckCircle2 } from 'lucide-react';

interface Budget {
    id: number; category: string; monthly_limit: number;
    spent: number; remaining: number; percent_used: number; is_over: boolean; notes?: string;
}

interface BudgetsProps {
    budgets: Budget[];
    categories: string[];
    year: number;
    month: number;
    can: { manage: boolean };
}

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const PRESET_CATEGORIES = ['Maintenance','Utilities','Salaries','Fuel','Chemicals','Equipment','Office Supplies','Other'];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Expenses', href: route('expenses.index') },
    { title: 'Budgets', href: '#' },
];

export default function ExpenseBudgets() {
    const { budgets, categories, year, month, can } = usePage<SharedData & BudgetsProps>().props;

    const allCategories = [...new Set([...PRESET_CATEGORIES, ...categories])].sort();

    const { data, setData, post, processing, errors, reset } = useForm({
        category: '',
        monthly_limit: '',
        year: String(year),
        month: String(month),
        notes: '',
    });

    const navigatePeriod = (y: number, m: number) =>
        router.get(route('expenses.budgets'), { year: y, month: m }, { preserveState: false });

    const prevMonth = () => {
        if (month === 1) navigatePeriod(year - 1, 12);
        else navigatePeriod(year, month - 1);
    };

    const nextMonth = () => {
        if (month === 12) navigatePeriod(year + 1, 1);
        else navigatePeriod(year, month + 1);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('expenses.budgets.store'), {
            onSuccess: () => reset('category', 'monthly_limit', 'notes'),
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('Remove this budget?'))
            router.delete(route('expenses.budgets.destroy', id), { preserveScroll: true });
    };

    const totalBudgeted = budgets.reduce((s, b) => s + b.monthly_limit, 0);
    const totalSpent    = budgets.reduce((s, b) => s + b.spent, 0);
    const overCount     = budgets.filter((b) => b.is_over).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Expense Budgets" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Expense Budgets</h1>
                        <p className="text-sm text-muted-foreground mt-1">Set monthly spending limits per category</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" onClick={prevMonth}>‹</Button>
                        <span className="font-medium text-sm min-w-[120px] text-center">{MONTHS[month - 1]} {year}</span>
                        <Button variant="outline" size="sm" onClick={nextMonth}>›</Button>
                    </div>
                </div>

                {/* Summary */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {[
                        { label: 'Total Budgeted', value: formatCurrency(totalBudgeted) },
                        { label: 'Total Spent (approved)', value: formatCurrency(totalSpent) },
                        { label: 'Over-budget Categories', value: `${overCount} of ${budgets.length}` },
                    ].map((c) => (
                        <Card key={c.label}>
                            <CardContent className="pt-5">
                                <p className="text-xs text-muted-foreground">{c.label}</p>
                                <p className="text-xl font-semibold mt-1">{c.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Budget list */}
                    <div className="lg:col-span-2 space-y-3">
                        <h2 className="font-semibold">{MONTHS[month - 1]} {year} Budgets</h2>
                        {budgets.length === 0 ? (
                            <Card>
                                <CardContent className="py-10 text-center text-muted-foreground">
                                    No budgets set for this month.
                                </CardContent>
                            </Card>
                        ) : (
                            budgets.map((budget) => (
                                <Card key={budget.id} className={budget.is_over ? 'border-red-300' : ''}>
                                    <CardContent className="pt-4 pb-4">
                                        <div className="flex items-center justify-between mb-2">
                                            <div className="flex items-center gap-2">
                                                {budget.is_over
                                                    ? <AlertTriangle size={16} className="text-red-500" />
                                                    : <CheckCircle2 size={16} className="text-green-500" />}
                                                <span className="font-medium">{budget.category}</span>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className="text-sm font-medium">{budget.percent_used}%</span>
                                                {can.manage && (
                                                    <Button variant="ghost" size="sm" onClick={() => handleDelete(budget.id)}>
                                                        <Trash size={14} className="text-red-500" />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                        <Progress value={Math.min(budget.percent_used, 100)}
                                            className={`h-2 mb-2 ${budget.is_over ? '[&>*]:bg-red-500' : '[&>*]:bg-green-500'}`} />
                                        <div className="flex justify-between text-xs text-muted-foreground">
                                            <span>Spent: <span className="font-medium text-foreground">{formatCurrency(budget.spent)}</span></span>
                                            <span>Budget: <span className="font-medium text-foreground">{formatCurrency(budget.monthly_limit)}</span></span>
                                            <span>
                                                {budget.is_over
                                                    ? <span className="text-red-600 font-medium">Over by {formatCurrency(Math.abs(budget.remaining))}</span>
                                                    : <span className="text-green-600">Remaining: {formatCurrency(budget.remaining)}</span>}
                                            </span>
                                        </div>
                                        {budget.notes && <p className="text-xs text-muted-foreground mt-2 italic">{budget.notes}</p>}
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </div>

                    {/* Add budget form */}
                    {can.manage && (
                        <div>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base flex items-center gap-2">
                                        <PlusCircle size={16} />Add Budget
                                    </CardTitle>
                                    <CardDescription>Set a spending limit for a category this month</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleSubmit} className="space-y-4">
                                        <div>
                                            <Label>Category</Label>
                                            <select value={data.category} onChange={(e) => setData('category', e.target.value)}
                                                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                                <option value="">Select category</option>
                                                {allCategories.map((c) => <option key={c} value={c}>{c}</option>)}
                                            </select>
                                            {errors.category && <p className="text-xs text-red-600 mt-1">{errors.category}</p>}
                                        </div>
                                        <div>
                                            <Label htmlFor="monthly_limit">Monthly Limit (KES)</Label>
                                            <Input id="monthly_limit" type="number" min="1" step="100"
                                                value={data.monthly_limit} onChange={(e) => setData('monthly_limit', e.target.value)}
                                                className="mt-1" placeholder="e.g. 50000" />
                                            {errors.monthly_limit && <p className="text-xs text-red-600 mt-1">{errors.monthly_limit}</p>}
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div>
                                                <Label>Year</Label>
                                                <Input type="number" min="2020" max="2100"
                                                    value={data.year} onChange={(e) => setData('year', e.target.value)}
                                                    className="mt-1" />
                                            </div>
                                            <div>
                                                <Label>Month</Label>
                                                <select value={data.month} onChange={(e) => setData('month', e.target.value)}
                                                    className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                                    {MONTHS.map((m, i) => <option key={i + 1} value={i + 1}>{m}</option>)}
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <Label htmlFor="notes">Notes (optional)</Label>
                                            <Input id="notes" value={data.notes}
                                                onChange={(e) => setData('notes', e.target.value)}
                                                placeholder="e.g. Increased due to repairs" className="mt-1" />
                                        </div>
                                        <Button type="submit" disabled={processing} className="w-full">
                                            {processing ? 'Saving...' : 'Save Budget'}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}