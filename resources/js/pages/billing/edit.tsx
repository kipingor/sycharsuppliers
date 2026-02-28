import { Head, useForm, usePage, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { formatCurrency } from '@/lib/utils';

interface Account {
    id: number;
    name: string;
    account_number: string;
}

interface Meter {
    id: number;
    meter_number: string;
    meter_name: string;
}

interface BillingDetail {
    id: number;
    meter_id: number;
    meter?: Meter;
    description?: string;
    units: number;
    rate: number;
    amount: number;
}

interface Bill {
    id: number;
    account_id: number;
    account?: Account;
    billing_period: string;
    issued_at: string;
    due_date: string;
    total_amount: number;
    status: string;
    notes?: string;
    details?: BillingDetail[];
}

interface EditBillingPageProps {
    bill: Bill;
    meters: Meter[];
    can: {
        update: boolean;
    };
}

export default function EditBilling() {
    const { bill, meters, can } = usePage<SharedData & EditBillingPageProps>().props;
    
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Bills', href: route('billings.index') },
        { title: `Bill #${bill.id}`, href: route('billings.show', bill.id) },
        { title: 'Edit', href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        notes: bill.notes || '',
        due_date: bill.due_date.split('T')[0], // Extract date portion
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('billings.update', bill.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Bill #${bill.id}`} />
            
            <div className="max-w-3xl mx-auto py-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Bill #{bill.id}</CardTitle>
                        <CardDescription>
                            Modify bill details. Note: Billing calculations are determined by meter readings 
                            and cannot be manually adjusted here.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!can.update && (
                            <Alert className="mb-4">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    You do not have permission to edit this bill.
                                </AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Bill Information (Read-only) */}
                            <div className="grid grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-md">
                                <div>
                                    <Label className="text-xs text-gray-500">Account</Label>
                                    <p className="text-sm font-medium">
                                        {bill.account?.name} ({bill.account?.account_number})
                                    </p>
                                </div>
                                <div>
                                    <Label className="text-xs text-gray-500">Billing Period</Label>
                                    <p className="text-sm font-medium">{bill.billing_period}</p>
                                </div>
                                <div>
                                    <Label className="text-xs text-gray-500">Status</Label>
                                    <p className="text-sm font-medium capitalize">{bill.status.replace('_', ' ')}</p>
                                </div>
                                <div>
                                    <Label className="text-xs text-gray-500">Total Amount</Label>
                                    <p className="text-sm font-medium">{formatCurrency(bill.total_amount)}</p>
                                </div>
                            </div>

                            {/* Editable Fields */}
                            <div>
                                <Label htmlFor="due_date">Due Date</Label>
                                <Input
                                    id="due_date"
                                    type="date"
                                    value={data.due_date}
                                    onChange={(e) => setData('due_date', e.target.value)}
                                    disabled={!can.update || processing}
                                    className="mt-1"
                                />
                                {errors.due_date && (
                                    <p className="text-sm text-red-600 mt-1">{errors.due_date}</p>
                                )}
                                <p className="text-sm text-gray-500 mt-1">
                                    The date by which payment is due
                                </p>
                            </div>

                            <div>
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    disabled={!can.update || processing}
                                    className="w-full min-h-[100px] rounded-md border border-input bg-background px-3 py-2 text-sm mt-1"
                                    placeholder="Add any notes about this bill..."
                                />
                                {errors.notes && (
                                    <p className="text-sm text-red-600 mt-1">{errors.notes}</p>
                                )}
                            </div>

                            {/* Billing Details (Read-only) */}
                            {bill.details && bill.details.length > 0 && (
                                <div>
                                    <Label className="mb-2 block">Billing Details (Read-only)</Label>
                                    <div className="border rounded-md overflow-hidden">
                                        <table className="w-full text-sm">
                                            <thead className="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th className="px-4 py-2 text-left">Meter</th>
                                                    <th className="px-4 py-2 text-right">Units</th>
                                                    <th className="px-4 py-2 text-right">Rate</th>
                                                    <th className="px-4 py-2 text-right">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {bill.details.map((detail) => (
                                                    <tr key={detail.id} className="border-t">
                                                        <td className="px-4 py-2">
                                                            {detail.meter?.meter_number || 'N/A'}
                                                            {detail.description && (
                                                                <span className="text-xs text-gray-500 block">
                                                                    {detail.description}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-2 text-right">{detail.units}</td>
                                                        <td className="px-4 py-2 text-right">{formatCurrency(detail.rate)}</td>
                                                        <td className="px-4 py-2 text-right font-medium">
                                                            {formatCurrency(detail.amount)}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    To modify billing amounts, you need to adjust the meter readings or use 
                                    the rebill function to recalculate the bill.
                                </AlertDescription>
                            </Alert>

                            <div className="flex gap-2 justify-end">
                                <Link href={route('billings.show', bill.id)}>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button 
                                    type="submit" 
                                    disabled={!can.update || processing}
                                >
                                    {processing ? 'Updating...' : 'Update Bill'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}