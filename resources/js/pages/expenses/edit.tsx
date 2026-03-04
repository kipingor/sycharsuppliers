import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Upload } from 'lucide-react';
import { useState } from 'react';

interface Expense {
    id: number; category: string; description: string; amount: number;
    expense_date: string; receipt_number?: string; receipt_path?: string;
}

interface EditExpenseProps {
    expense: Expense;
    categories: string[];
}

const PRESET_CATEGORIES = ['Maintenance', 'Utilities', 'Salaries', 'Fuel', 'Chemicals', 'Equipment', 'Office Supplies', 'Other'];

export default function EditExpense() {
    const { expense, categories } = usePage<SharedData & EditExpenseProps>().props;
    const [filePreview, setFilePreview] = useState<string | null>(null);

    const allCategories = [...new Set([...PRESET_CATEGORIES, ...categories])].sort();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Expenses', href: route('expenses.index') },
        { title: `Expense #${expense.id}`, href: route('expenses.show', expense.id) },
        { title: 'Edit', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm<{
        category: string; description: string; amount: string;
        expense_date: string; receipt_number: string; receipt_file: File | null; _method: string;
    }>({
        _method: 'PUT',
        category: expense.category,
        description: expense.description,
        amount: String(expense.amount),
        expense_date: expense.expense_date,
        receipt_number: expense.receipt_number || '',
        receipt_file: null,
    });

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
        post(route('expenses.update', expense.id), { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Expense #${expense.id}`} />
            <div className="max-w-2xl mx-auto py-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Expense #{expense.id}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-5" encType="multipart/form-data">
                            <div>
                                <Label>Category *</Label>
                                <select value={data.category} onChange={(e) => setData('category', e.target.value)}
                                    disabled={processing}
                                    className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <option value="">Select category</option>
                                    {allCategories.map((c) => <option key={c} value={c}>{c}</option>)}
                                </select>
                                {errors.category && <p className="text-sm text-red-600 mt-1">{errors.category}</p>}
                            </div>

                            <div>
                                <Label htmlFor="description">Description *</Label>
                                <Textarea id="description" value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    disabled={processing} className="mt-1" rows={3} />
                                {errors.description && <p className="text-sm text-red-600 mt-1">{errors.description}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="amount">Amount (KES) *</Label>
                                    <Input id="amount" type="number" step="0.01" min="0.01"
                                        value={data.amount} onChange={(e) => setData('amount', e.target.value)}
                                        disabled={processing} className="mt-1" />
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

                            <div>
                                <Label htmlFor="receipt_number">Receipt / Reference Number</Label>
                                <Input id="receipt_number" value={data.receipt_number}
                                    onChange={(e) => setData('receipt_number', e.target.value)}
                                    disabled={processing} className="mt-1" />
                            </div>

                            <div>
                                <Label>Replace Receipt File (optional)</Label>
                                {expense.receipt_path && !filePreview && (
                                    <p className="text-xs text-muted-foreground mb-1">Current file: {expense.receipt_path.split('/').pop()}</p>
                                )}
                                <label className="mt-1 flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-input rounded-lg cursor-pointer hover:bg-muted/50 transition-colors">
                                    <Upload size={18} className="mb-1 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        {data.receipt_file ? data.receipt_file.name : 'Click to replace receipt'}
                                    </span>
                                    <input type="file" accept="image/jpeg,image/png,application/pdf"
                                        onChange={handleFileChange} className="hidden" />
                                </label>
                                {filePreview && <img src={filePreview} alt="Preview" className="mt-2 h-28 rounded border object-contain" />}
                                {errors.receipt_file && <p className="text-sm text-red-600 mt-1">{errors.receipt_file}</p>}
                            </div>

                            <div className="flex gap-2 justify-end pt-2">
                                <Link href={route('expenses.show', expense.id)}>
                                    <Button type="button" variant="outline">Cancel</Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}