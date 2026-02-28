import { Head, useForm, usePage, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { AlertCircle, DollarSign } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { formatCurrency } from '@/lib/utils';

interface Account {
    id: number;
    name: string;
    account_number: string;
    outstanding_balance?: number;
}

interface PaymentMethod {
    value: string;
    label: string;
}

interface CreatePaymentPageProps {
    accounts: Account[];
    paymentMethods: PaymentMethod[];
    can: {
        create: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payments', href: route('payments.index') },
    { title: 'Record Payment', href: '#' },
];

const defaultPaymentMethods: PaymentMethod[] = [
    { value: 'Cash', label: 'Cash' },
    { value: 'M-Pesa', label: 'M-Pesa' },
    { value: 'Bank Transfer', label: 'Bank Transfer' },
    { value: 'Cheque', label: 'Cheque' },
    { value: 'Card', label: 'Card' },
];

export default function CreatePayment() {
    const { accounts, paymentMethods, can } = usePage<SharedData & CreatePaymentPageProps>().props;
    
    const methods = paymentMethods && paymentMethods.length > 0 ? paymentMethods : defaultPaymentMethods;
    
    const { data, setData, post, processing, errors } = useForm({
        account_id: '',
        payment_date: new Date().toISOString().split('T')[0],
        amount: '',
        method: '',
        reference: '',
        transaction_id: '',
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('payments.store'));
    };

    const selectedAccount = accounts.find(acc => acc.id === Number(data.account_id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Record Payment" />
            
            <div className="max-w-3xl mx-auto py-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Record New Payment</CardTitle>
                        <CardDescription>
                            Record a payment received from an account. The system will automatically 
                            allocate the payment to outstanding bills.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!can.create && (
                            <Alert className="mb-4">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    You do not have permission to record payments.
                                </AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Account Selection */}
                            <div>
                                <Label htmlFor="account_id">Account *</Label>
                                <Select
                                    value={data.account_id}
                                    onValueChange={(value) => setData('account_id', value)}
                                    disabled={!can.create || processing}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select account" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((account) => (
                                            <SelectItem key={account.id} value={account.id.toString()}>
                                                <div className="flex items-center justify-between w-full">
                                                    <span>
                                                        {account.name} ({account.account_number})
                                                    </span>
                                                    {account.outstanding_balance !== undefined && (
                                                        <span className="ml-4 text-xs text-gray-500">
                                                            Bal: {formatCurrency(account.outstanding_balance)}
                                                        </span>
                                                    )}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.account_id && (
                                    <p className="text-sm text-red-600 mt-1">{errors.account_id}</p>
                                )}
                                {selectedAccount?.outstanding_balance !== undefined && (
                                    <p className="text-sm text-gray-500 mt-1">
                                        Outstanding balance: {formatCurrency(selectedAccount.outstanding_balance)}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                {/* Payment Date */}
                                <div>
                                    <Label htmlFor="payment_date">Payment Date *</Label>
                                    <Input
                                        id="payment_date"
                                        type="date"
                                        value={data.payment_date}
                                        onChange={(e) => setData('payment_date', e.target.value)}
                                        disabled={!can.create || processing}
                                        className="mt-1"
                                        max={new Date().toISOString().split('T')[0]}
                                    />
                                    {errors.payment_date && (
                                        <p className="text-sm text-red-600 mt-1">{errors.payment_date}</p>
                                    )}
                                </div>

                                {/* Amount */}
                                <div>
                                    <Label htmlFor="amount">Amount *</Label>
                                    <div className="relative mt-1">
                                        <DollarSign className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500" />
                                        <Input
                                            id="amount"
                                            type="number"
                                            step="1.00"
                                            min="0"
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                            disabled={!can.create || processing}
                                            className="pl-9"
                                            placeholder="0.00"
                                        />
                                    </div>
                                    {errors.amount && (
                                        <p className="text-sm text-red-600 mt-1">{errors.amount}</p>
                                    )}
                                </div>
                            </div>

                            {/* Payment Method */}
                            <div>
                                <Label htmlFor="method">Payment Method *</Label>
                                <Select
                                    value={data.method}
                                    onValueChange={(value) => setData('method', value)}
                                    disabled={!can.create || processing}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select payment method" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {methods.map((method) => (
                                            <SelectItem key={method.value} value={method.value}>
                                                {method.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.method && (
                                    <p className="text-sm text-red-600 mt-1">{errors.method}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                {/* Transaction ID */}
                                <div>
                                    <Label htmlFor="transaction_id">Transaction ID</Label>
                                    <Input
                                        id="transaction_id"
                                        type="text"
                                        value={data.transaction_id}
                                        onChange={(e) => setData('transaction_id', e.target.value)}
                                        disabled={!can.create || processing}
                                        className="mt-1"
                                        placeholder="e.g., MPesa code"
                                    />
                                    {errors.transaction_id && (
                                        <p className="text-sm text-red-600 mt-1">{errors.transaction_id}</p>
                                    )}
                                </div>

                                {/* Reference */}
                                <div>
                                    <Label htmlFor="reference">Reference Number</Label>
                                    <Input
                                        id="reference"
                                        type="text"
                                        value={data.reference}
                                        onChange={(e) => setData('reference', e.target.value)}
                                        disabled={!can.create || processing}
                                        className="mt-1"
                                        placeholder="e.g., Receipt number"
                                    />
                                    {errors.reference && (
                                        <p className="text-sm text-red-600 mt-1">{errors.reference}</p>
                                    )}
                                </div>
                            </div>

                            {/* Notes */}
                            <div>
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    disabled={!can.create || processing}
                                    className="w-full min-h-[100px] rounded-md border border-input bg-background px-3 py-2 text-sm mt-1"
                                    placeholder="Add any additional notes about this payment..."
                                />
                                {errors.notes && (
                                    <p className="text-sm text-red-600 mt-1">{errors.notes}</p>
                                )}
                            </div>

                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    After recording, the payment will be automatically allocated to the 
                                    oldest outstanding bills for this account.
                                </AlertDescription>
                            </Alert>

                            <div className="flex gap-2 justify-end">
                                <Link href={route('payments.index')}>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button 
                                    type="submit" 
                                    disabled={!can.create || processing}
                                >
                                    {processing ? 'Recording...' : 'Record Payment'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}