import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { AlertTriangle, Upload, Info } from 'lucide-react';
import { useState } from 'react';

interface Budget {
    category: string; monthly_limit: number; spent: number; remaining: number; percent_used: number;
}

interface CreateExpenseProps {
    categories: string[];
    budgets: Budget[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Expenses', href: route('expenses.index') },
    { title: 'Add Expense', href: '#' },
];

const PRESET_CATEGORIES = ['Maintenance', 'Utilities', 'Salaries', 'Fuel', 'Chemicals', 'Equipment', 'Office Supplies', 'Other'];

export default function CreateExpense() {
    const { categories, budgets } = usePage<SharedData & CreateExpenseProps>().props;
    const [customCategory, setCustomCategory] = useState(false);
    const [filePreview, setFilePreview] = useState<string | null>(null);

    const allCategories = [...new Set([...PRESET_CATEGORIES, ...categories])].sort();

    const { data, setData, post, processing, errors } = useForm<{
        category: string; description: string; amount: string;
        expense_date: string; receipt_number: string; receipt_file: File | null;
    }>({
        category: '',
        description: '',
        amount: '',
        expense_date: new Date().toISOString().split('T')[0],
        receipt_number: '',
        receipt_file: null,
    });

    const activeBudget = budgets.find((b) => b.category === data.category);
    const wouldExceedBudget = activeBudget && parseFloat(data.amount || '0') > activeBudget.remaining;

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setData('receipt_file', file);
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (ev) => setFilePreview(ev.target?.result as string);
            reader.readAsDataURL(file);
        } else {
            setFilePreview(null);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('expenses.store'), { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Expense" />
            <div className="max-w-2xl mx-auto py-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Add Expense</CardTitle>
                        <CardDescription>Record an operational expense. Expenses require admin approval before they are counted in reports.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-5" encType="multipart/form-data">

                            {/* Category */}
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <Label htmlFor="category">Category *</Label>
                                    <button type="button" onClick={() => setCustomCategory(!customCategory)}
                                        className="text-xs text-blue-600 hover:underline">
                                        {customCategory ? 'Use preset' : 'Custom category'}
                                    </button>
                                </div>
                                {customCategory ? (
                                    <Input id="category" value={data.category}
                                        onChange={(e) => setData('category', e.target.value)}
                                        placeholder="e.g. Generator Fuel" disabled={processing} />
                                ) : (
                                    <select value={data.category} onChange={(e) => setData('category', e.target.value)}
                                        disabled={processing}
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                        <option value="">Select category</option>
                                        {allCategories.map((c) => <option key={c} value={c}>{c}</option>)}
                                    </select>
                                )}
                                {errors.category && <p className="text-sm text-red-600 mt-1">{errors.category}</p>}
                            </div>

                            {/* Budget indicator */}
                            {activeBudget && (
                                <div className={`rounded-lg p-3 text-sm ${wouldExceedBudget ? 'bg-red-50 dark:bg-red-950/30 border border-red-200' : 'bg-blue-50 dark:bg-blue-950/30'}`}>
                                    <div className="flex items-center gap-2 mb-2">
                                        {wouldExceedBudget
                                            ? <AlertTriangle size={14} className="text-red-600" />
                                            : <Info size={14} className="text-blue-600" />}
                                        <span className="font-medium">{data.category} budget this month</span>
                                    </div>
                                    <Progress value={Math.min(activeBudget.percent_used, 100)}
                                        className={`h-1.5 mb-1 ${wouldExceedBudget ? '[&>*]:bg-red-500' : ''}`} />
                                    <p className="text-xs text-muted-foreground">
                                        KES {activeBudget.spent.toLocaleString()} spent of KES {activeBudget.monthly_limit.toLocaleString()} budget
                                        {wouldExceedBudget && ' — this expense will exceed the budget'}
                                    </p>
                                </div>
                            )}

                            {/* Description */}
                            <div>
                                <Label htmlFor="description">Description *</Label>
                                <Textarea id="description" value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="What was this expense for?" disabled={processing}
                                    className="mt-1" rows={3} />
                                {errors.description && <p className="text-sm text-red-600 mt-1">{errors.description}</p>}
                            </div>

                            {/* Amount + Date */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="amount">Amount (KES) *</Label>
                                    <Input id="amount" type="number" step="0.01" min="0.01"
                                        value={data.amount} onChange={(e) => setData('amount', e.target.value)}
                                        placeholder="0.00" disabled={processing} className="mt-1" />
                                    {errors.amount && <p className="text-sm text-red-600 mt-1">{errors.amount}</p>}
                                </div>
                                <div>
                                    <Label htmlFor="expense_date">Date *</Label>
                                    <Input id="expense_date" type="date" value={data.expense_date}
                                        onChange={(e) => setData('expense_date', e.target.value)}
                                        max={new Date().toISOString().split('T')[0]}
                                        disabled={processing} className="mt-1" />
                                    {errors.expense_date && <p className="text-sm text-red-600 mt-1">{errors.expense_date}</p>}
                                </div>
                            </div>

                            {/* Receipt number */}
                            <div>
                                <Label htmlFor="receipt_number">Receipt / Reference Number</Label>
                                <Input id="receipt_number" value={data.receipt_number}
                                    onChange={(e) => setData('receipt_number', e.target.value)}
                                    placeholder="e.g. RCT-001" disabled={processing} className="mt-1" />
                                {errors.receipt_number && <p className="text-sm text-red-600 mt-1">{errors.receipt_number}</p>}
                            </div>

                            {/* File upload */}
                            <div>
                                <Label>Receipt File (JPG, PNG, PDF — max 5 MB)</Label>
                                <label className="mt-1 flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-input rounded-lg cursor-pointer hover:bg-muted/50 transition-colors">
                                    <Upload size={20} className="mb-1 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        {data.receipt_file ? data.receipt_file.name : 'Click to upload receipt'}
                                    </span>
                                    <input type="file" accept="image/jpeg,image/png,application/pdf"
                                        onChange={handleFileChange} className="hidden" />
                                </label>
                                {filePreview && (
                                    <img src={filePreview} alt="Receipt preview" className="mt-2 h-32 rounded border object-contain" />
                                )}
                                {errors.receipt_file && <p className="text-sm text-red-600 mt-1">{errors.receipt_file}</p>}
                            </div>

                            {wouldExceedBudget && (
                                <Alert className="border-orange-300 bg-orange-50 dark:bg-orange-950/20">
                                    <AlertTriangle className="h-4 w-4 text-orange-600" />
                                    <AlertDescription className="text-orange-800 dark:text-orange-300">
                                        This expense exceeds the monthly budget for <strong>{data.category}</strong>. You can still submit — the approver will be aware.
                                    </AlertDescription>
                                </Alert>
                            )}

                            <div className="flex gap-2 justify-end pt-2">
                                <Link href={route('expenses.index')}>
                                    <Button type="button" variant="outline">Cancel</Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Expense'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}