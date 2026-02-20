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

interface Meter {
    id: number;
    meter_number: string;
    meter_name: string;
    meter_type: 'individual' | 'bulk';
    account_id?: number;
    bulk_meter_id?: number;
    status: string;
    location?: string;
    installation_date?: string;
    initial_reading: number;
    notes?: string;
}

interface EditMeterPageProps {
    meter: Meter;
    accounts: Account[];
    bulkMeters: BulkMeter[];
    can: {
        update: boolean;
    };
}

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

export default function EditMeter() {
    const { meter, accounts, bulkMeters, can } = usePage<SharedData & EditMeterPageProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Meters', href: route('meters.index') },
        { title: meter.meter_number, href: route('meters.show', meter.id) },
        { title: 'Edit', href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        meter_number: meter.meter_number,
        meter_name: meter.meter_name,
        account_id: meter.account_id?.toString() || '',
        meter_type: meter.meter_type,
        bulk_meter_id: meter.bulk_meter_id?.toString() || '',
        status: meter.status,
        location: meter.location || '',
        installation_date: meter.installation_date?.split('T')[0] || '',
        notes: meter.notes || '',
        is_sub_meter: !!meter.bulk_meter_id,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('meters.update', meter.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Meter ${meter.meter_number}`} />
            
            <div className="max-w-3xl mx-auto py-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Gauge className="h-6 w-6" />
                            <CardTitle>Edit Meter {meter.meter_number}</CardTitle>
                        </div>
                        <CardDescription>
                            Update meter information and settings.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!can.update && (
                            <Alert className="mb-4">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    You do not have permission to edit this meter.
                                </AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Read-only Info */}
                            <div className="grid grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-md">
                                <div>
                                    <Label className="text-xs text-gray-500">Meter ID</Label>
                                    <p className="text-sm font-medium">#{meter.id}</p>
                                </div>
                                <div>
                                    <Label className="text-xs text-gray-500">Initial Reading</Label>
                                    <p className="text-sm font-medium">{meter.initial_reading} m³</p>
                                </div>
                            </div>

                            {/* Meter Type (Read-only after creation) */}
                            <div>
                                <Label htmlFor="meter_type">Meter Type</Label>
                                <Select
                                    value={data.meter_type}
                                    onValueChange={(value: "individual" | "bulk") => {
                                        setData('meter_type', value);
                                        if (value === 'individual') {
                                            setData('is_sub_meter', false);
                                        }
                                    }}
                                    disabled={true} // Usually can't change meter type after creation
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
                                <p className="text-sm text-gray-500 mt-1">
                                    Meter type cannot be changed after creation
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
                                        disabled={!can.update || processing}
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
                                        disabled={!can.update || processing}
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
                                        disabled={!can.update || processing}
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
                                            disabled={!can.update || processing}
                                        />
                                    </div>

                                    {data.is_sub_meter && (
                                        <div>
                                            <Label htmlFor="bulk_meter_id">Parent Bulk Meter *</Label>
                                            <Select
                                                value={data.bulk_meter_id}
                                                onValueChange={(value) => setData('bulk_meter_id', value)}
                                                disabled={!can.update || processing}
                                            >
                                                <SelectTrigger className="mt-1">
                                                    <SelectValue placeholder="Select bulk meter" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {bulkMeters.map((bulkMeter) => (
                                                        <SelectItem key={bulkMeter.id} value={bulkMeter.id.toString()}>
                                                            {bulkMeter.meter_number} - {bulkMeter.meter_name}
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
                                        disabled={!can.update || processing}
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

                                {/* Installation Date */}
                                <div>
                                    <Label htmlFor="installation_date">Installation Date</Label>
                                    <Input
                                        id="installation_date"
                                        type="date"
                                        value={data.installation_date}
                                        onChange={(e) => setData('installation_date', e.target.value)}
                                        disabled={!can.update || processing}
                                        className="mt-1"
                                        max={new Date().toISOString().split('T')[0]}
                                    />
                                    {errors.installation_date && (
                                        <p className="text-sm text-red-600 mt-1">{errors.installation_date}</p>
                                    )}
                                </div>
                            </div>

                            {/* Location */}
                            <div>
                                <Label htmlFor="location">Location</Label>
                                <Input
                                    id="location"
                                    type="text"
                                    value={data.location}
                                    onChange={(e) => setData('location', e.target.value)}
                                    disabled={!can.update || processing}
                                    className="mt-1"
                                    placeholder="e.g., Ground floor utility room"
                                />
                                {errors.location && (
                                    <p className="text-sm text-red-600 mt-1">{errors.location}</p>
                                )}
                            </div>

                            {/* Notes */}
                            <div>
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    disabled={!can.update || processing}
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
                                    Note: Meter type and initial reading cannot be changed after creation. 
                                    Use meter readings to track consumption changes.
                                </AlertDescription>
                            </Alert>

                            <div className="flex gap-2 justify-end">
                                <Link href={route('meters.show', meter.id)}>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button 
                                    type="submit" 
                                    disabled={!can.update || processing}
                                >
                                    {processing ? 'Updating...' : 'Update Meter'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}