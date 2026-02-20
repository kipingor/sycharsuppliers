import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle } from 'lucide-react';

interface Account {
    id: number;
    name: string;
    account_number: string;
}

interface CreateBillingPageProps {
    accounts: Account[];
    current_month: string;
    total_accounts: number;
    unbilled_accounts: number;
    can: {
        create: boolean;
        generate: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bills', href: route('billings.index') },
    { title: 'Generate Bill', href: '#' },
];

export default function CreateBilling() {
    const { accounts, unbilled_accounts, total_accounts, current_month, can } = usePage<SharedData & CreateBillingPageProps>().props;

    const { data, setData, post, processing, errors } = useForm({
        account_id: '',
        billing_period: new Date().toISOString().slice(0, 7), // YYYY-MM format
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('billings.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Generate Bill" />

            <div className="mx-auto max-w-2xl py-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Generate New Bill</CardTitle>
                        <CardDescription>Generate a bill for an account based on their meter readings for the selected period.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!can.generate && (
                            <Alert className="mb-4">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>You do not have permission to generate bills.</AlertDescription>
                            </Alert>
                        )}

                        <Alert>
                            <AlertDescription>
                                {unbilled_accounts} of {total_accounts} accounts need billing for {current_month}
                            </AlertDescription>
                        </Alert>

                        {accounts.length === 0 && (
                            <Alert variant="default" className="mb-4">
                                <CheckCircle className="h-4 w-4" />
                                <AlertDescription>All accounts have been billed for {current_month}. Great work!</AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div>
                                <Label htmlFor="account_id">Account *</Label>
                                <Select
                                    value={data.account_id}
                                    onValueChange={(value) => setData('account_id', value)}
                                    disabled={!can.generate || processing}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select account" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((account) => (
                                            <SelectItem key={account.id} value={account.id.toString()}>
                                                {account.name} ({account.account_number})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.account_id && <p className="mt-1 text-sm text-red-600">{errors.account_id}</p>}
                                <p className="mt-1 text-sm text-gray-500">Select the account to generate a bill for</p>
                            </div>

                            <div>
                                <Label htmlFor="billing_period">Billing Period *</Label>
                                <Input
                                    id="billing_period"
                                    type="month"
                                    value={data.billing_period}
                                    onChange={(e) => setData('billing_period', e.target.value)}
                                    disabled={!can.generate || processing}
                                    className="mt-1"
                                />
                                {errors.billing_period && <p className="mt-1 text-sm text-red-600">{errors.billing_period}</p>}
                                <p className="mt-1 text-sm text-gray-500">The month and year for which to generate the bill</p>
                            </div>

                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    The system will automatically calculate consumption based on meter readings and apply the appropriate tariff
                                    rates.
                                </AlertDescription>
                            </Alert>

                            <div className="flex justify-end gap-2">
                                <Link href={route('billings.index')}>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={!can.generate || processing}>
                                    {processing ? 'Generating...' : 'Generate Bill'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
