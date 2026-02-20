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
import { AlertCircle, Gauge } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Switch } from '@/components/ui/switch';

interface Account {
    id: number;
    name: string;
    account_number: string;
}

interface BulkMeter {
    id: number;
    meter_number: string;
    meter_name: string;
}

interface CreateMeterPageProps {
    accounts: Account[];
    bulkMeters: BulkMeter[];
    can: {
        create: boolean;
    };
}

type CreateMeterForm = {
    meter_number: string;
    meter_name: string;
    account_id: string;
    meter_type: string;
    bulk_meter_id: string;
    status: string;
    initial_reading: string;
    location: string;
    installation_date: string;
    notes: string;
    is_sub_meter: boolean;
} & Record<string, import('@inertiajs/core').FormDataConvertible>;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Meters', href: route('meters.index') },
    { title: 'Add Meter', href: '#' },
];

const meterTypes = [
    { value: 'individual', label: 'Individual Meter' },
    { value: 'bulk', label: 'Bulk Meter' },
];

const meterStatuses = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'maintenance', label: 'Under Maintenance' },
    { value: 'faulty', label: 'Faulty' },
];

export default function CreateMeter() {
    const { accounts, bulkMeters, can } = usePage<SharedData & CreateMeterPageProps>().props;
    
    const { data, setData, post, processing, errors } = useForm<CreateMeterForm>({
        meter_number: '',
        meter_name: '',
        account_id: '',
        meter_type: 'individual',
        bulk_meter_id: '',
        status: 'active',
        initial_reading: '0',
        location: '',
        installation_date: new Date().toISOString().split('T')[0],
        notes: '',
        is_sub_meter: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('meters.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Meter" />
            
            <div className="max-w-3xl mx-auto py-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Gauge className="h-6 w-6" />
                            <CardTitle>Add New Meter</CardTitle>
                        </div>
                        <CardDescription>
                            Register a new water meter for consumption tracking and billing.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!can.create && (
                            <Alert className="mb-4">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    You do not have permission to create meters.
                                </AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Meter Type */}
                            <div>
                                <Label htmlFor="meter_type">Meter Type *</Label>
                                <Select
                                    value={data.meter_type}
                                    onValueChange={(value) => {
                                        setData('meter_type', value);
                                        if (value === 'individual') {
                                            setData('is_sub_meter', false);
                                        }
                                    }}
                                    disabled={!can.create || processing}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select meter type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {meterTypes.map((type) => (
                                            <SelectItem key={type.value} value={type.value}>
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.meter_type && (
                                    <p className="text-sm text-red-600 mt-1">{errors.meter_type}</p>
                                )}
                                <p className="text-sm text-gray-500 mt-1">
                                    {data.meter_type === 'individual' 
                                        ? 'Meter assigned to a single account' 
                                        : 'Meter that serves multiple accounts (e.g., building main meter)'}
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                {/* Meter Number */}
                                <div>
                                    <Label htmlFor="meter_number">Meter Number *</Label>
                                    <Input
                                        id="meter_number"
                                        type="text"
                                        value={data.meter_number}
                                        onChange={(e) => setData('meter_number', e.target.value)}
                                        disabled={!can.create || processing}
                                        className="mt-1"
                                        placeholder="e.g., MTR-001"
                                    />
                                    {errors.meter_number && (
                                        <p className="text-sm text-red-600 mt-1">{errors.meter_number}</p>
                                    )}
                                </div>

                                {/* Meter Name */}
                                <div>
                                    <Label htmlFor="meter_name">Meter Name *</Label>
                                    <Input
                                        id="meter_name"
                                        type="text"
                                        value={data.meter_name}
                                        onChange={(e) => setData('meter_name', e.target.value)}
                                        disabled={!can.create || processing}
                                        className="mt-1"
                                        placeholder="e.g., Unit 101 Main"
                                    />
                                    {errors.meter_name && (
                                        <p className="text-sm text-red-600 mt-1">{errors.meter_name}</p>
                                    )}
                                </div>
                            </div>

                            {/* Account Selection (for individual meters) */}
                            {data.meter_type === 'individual' && (
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
                                                    {account.name} ({account.account_number})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.account_id && (
                                        <p className="text-sm text-red-600 mt-1">{errors.account_id}</p>
                                    )}
                                </div>
                            )}

                            {/* Sub-meter Option (for individual meters) */}
                            {data.meter_type === 'individual' && bulkMeters.length > 0 && (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <Label htmlFor="is_sub_meter">Is this a sub-meter?</Label>
                                            <p className="text-sm text-gray-500">
                                                Sub-meters are connected to a bulk meter
                                            </p>
                                        </div>
                                        <Switch
                                            id="is_sub_meter"
                                            checked={data.is_sub_meter}
                                            onCheckedChange={(checked) => {
                                                setData('is_sub_meter', checked);
                                                if (!checked) {
                                                    setData('bulk_meter_id', '');
                                                }
                                            }}
                                            disabled={!can.create || processing}
                                        />
                                    </div>

                                    {data.is_sub_meter && (
                                        <div>
                                            <Label htmlFor="bulk_meter_id">Parent Bulk Meter *</Label>
                                            <Select
                                                value={data.bulk_meter_id}
                                                onValueChange={(value) => setData('bulk_meter_id', value)}
                                                disabled={!can.create || processing}
                                            >
                                                <SelectTrigger className="mt-1">
                                                    <SelectValue placeholder="Select bulk meter" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {bulkMeters.map((meter) => (
                                                        <SelectItem key={meter.id} value={meter.id.toString()}>
                                                            {meter.meter_number} - {meter.meter_name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.bulk_meter_id && (
                                                <p className="text-sm text-red-600 mt-1">{errors.bulk_meter_id}</p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                {/* Status */}
                                <div>
                                    <Label htmlFor="status">Status *</Label>
                                    <Select
                                        value={data.status}
                                        onValueChange={(value) => setData('status', value)}
                                        disabled={!can.create || processing}
                                    >
                                        <SelectTrigger className="mt-1">
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {meterStatuses.map((status) => (
                                                <SelectItem key={status.value} value={status.value}>
                                                    {status.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.status && (
                                        <p className="text-sm text-red-600 mt-1">{errors.status}</p>
                                    )}
                                </div>

                                {/* Initial Reading */}
                                <div>
                                    <Label htmlFor="initial_reading">Initial Reading *</Label>
                                    <Input
                                        id="initial_reading"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.initial_reading}
                                        onChange={(e) => setData('initial_reading', e.target.value)}
                                        disabled={!can.create || processing}
                                        className="mt-1"
                                        placeholder="0"
                                    />
                                    {errors.initial_reading && (
                                        <p className="text-sm text-red-600 mt-1">{errors.initial_reading}</p>
                                    )}
                                    <p className="text-sm text-gray-500 mt-1">
                                        Current reading on the meter (cubic meters)
                                    </p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                {/* Location */}
                                <div>
                                    <Label htmlFor="location">Location</Label>
                                    <Input
                                        id="location"
                                        type="text"
                                        value={data.location}
                                        onChange={(e) => setData('location', e.target.value)}
                                        disabled={!can.create || processing}
                                        className="mt-1"
                                        placeholder="e.g., Ground floor utility room"
                                    />
                                    {errors.location && (
                                        <p className="text-sm text-red-600 mt-1">{errors.location}</p>
                                    )}
                                </div>

                                {/* Installation Date */}
                                <div>
                                    <Label htmlFor="installation_date">Installation Date</Label>
                                    <Input
                                        id="installation_date"
                                        type="date"
                                        value={data.installation_date}
                                        onChange={(e) => setData('installation_date', e.target.value)}
                                        disabled={!can.create || processing}
                                        className="mt-1"
                                        max={new Date().toISOString().split('T')[0]}
                                    />
                                    {errors.installation_date && (
                                        <p className="text-sm text-red-600 mt-1">{errors.installation_date}</p>
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
                                    placeholder="Add any additional notes about this meter..."
                                />
                                {errors.notes && (
                                    <p className="text-sm text-red-600 mt-1">{errors.notes}</p>
                                )}
                            </div>

                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    Once created, you can start recording meter readings and generating bills 
                                    for this meter.
                                </AlertDescription>
                            </Alert>

                            <div className="flex gap-2 justify-end">
                                <Link href={route('meters.index')}>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button 
                                    type="submit" 
                                    disabled={!can.create || processing}
                                >
                                    {processing ? 'Creating...' : 'Create Meter'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}