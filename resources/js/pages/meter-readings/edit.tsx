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
import { AlertCircle, Activity } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface Meter {
    id: number;
    meter_number: string;
    meter_name: string;
}

interface MeterReading {
    id: number;
    meter_id: number;
    meter?: Meter;
    reading_date: string;
    reading_value: number;
    reading_type: string;
    notes?: string;
}

interface EditReadingPageProps {
    reading: MeterReading;
    meters: Meter[];
    can: {
        update: boolean;
    };
}

const readingTypes = [
    { value: 'actual', label: 'Actual Reading' },
    { value: 'estimated', label: 'Estimated Reading' },
    { value: 'correction', label: 'Correction' },
];

export default function EditMeterReading() {
    const { reading, meters, can } = usePage<SharedData & EditReadingPageProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Meter Readings', href: route('meter-readings.index') },
        { title: `Reading #${reading.id}`, href: route('meter-readings.show', reading.id) },
        { title: 'Edit', href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        meter_id: reading.meter_id.toString(),
        reading_date: reading.reading_date.split('T')[0],
        reading_value: reading.reading_value.toString(),
        reading_type: reading.reading_type,
        notes: reading.notes || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('meter-readings.update', reading.id));
    };

    const valueChanged = data.reading_value !== reading.reading_value.toString();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Reading #${reading.id}`} />
            
            <div className="max-w-3xl mx-auto py-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Activity className="h-6 w-6" />
                            <CardTitle>Edit Reading #{reading.id}</CardTitle>
                        </div>
                        <CardDescription>
                            Modify meter reading details. Changes may affect consumption calculations 
                            and billing.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!can.update && (
                            <Alert className="mb-4">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    You do not have permission to edit this reading.
                                </AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Read-only Info */}
                            <div className="grid grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-md">
                                <div>
                                    <Label className="text-xs text-gray-500">Reading ID</Label>
                                    <p className="text-sm font-medium">#{reading.id}</p>
                                </div>
                                <div>
                                    <Label className="text-xs text-gray-500">Original Value</Label>
                                    <p className="text-sm font-medium">{reading.reading_value} m³</p>
                                </div>
                            </div>

                            {/* Meter Selection */}
                            <div>
                                <Label htmlFor="meter_id">Meter *</Label>
                                <Select
                                    value={data.meter_id}
                                    onValueChange={(value) => setData('meter_id', value)}
                                    disabled={!can.update || processing}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select meter" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {meters.map((meter) => (
                                            <SelectItem key={meter.id} value={meter.id.toString()}>
                                                {meter.meter_number} - {meter.meter_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.meter_id && (
                                    <p className="text-sm text-red-600 mt-1">{errors.meter_id}</p>
                                )}
                                <p className="text-sm text-gray-500 mt-1">
                                    ⚠️ Changing the meter will affect which meter this reading is associated with
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                {/* Reading Date */}
                                <div>
                                    <Label htmlFor="reading_date">Reading Date *</Label>
                                    <Input
                                        id="reading_date"
                                        type="date"
                                        value={data.reading_date}
                                        onChange={(e) => setData('reading_date', e.target.value)}
                                        disabled={!can.update || processing}
                                        className="mt-1"
                                        max={new Date().toISOString().split('T')[0]}
                                    />
                                    {errors.reading_date && (
                                        <p className="text-sm text-red-600 mt-1">{errors.reading_date}</p>
                                    )}
                                </div>

                                {/* Reading Value */}
                                <div>
                                    <Label htmlFor="reading_value">Reading Value (m³) *</Label>
                                    <div className="relative mt-1">
                                        <Input
                                            id="reading_value"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.reading_value}
                                            onChange={(e) => setData('reading_value', e.target.value)}
                                            disabled={!can.update || processing}
                                            placeholder="0.00"
                                        />
                                    </div>
                                    {errors.reading_value && (
                                        <p className="text-sm text-red-600 mt-1">{errors.reading_value}</p>
                                    )}
                                    {valueChanged && (
                                        <p className="text-sm text-yellow-600 mt-1">
                                            ⚠️ Changing the value will recalculate consumption
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Reading Type */}
                            <div>
                                <Label htmlFor="reading_type">Reading Type *</Label>
                                <Select
                                    value={data.reading_type}
                                    onValueChange={(value) => setData('reading_type', value)}
                                    disabled={!can.update || processing}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select reading type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {readingTypes.map((type) => (
                                            <SelectItem key={type.value} value={type.value}>
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.reading_type && (
                                    <p className="text-sm text-red-600 mt-1">{errors.reading_type}</p>
                                )}
                                <p className="text-sm text-gray-500 mt-1">
                                    {data.reading_type === 'actual' && 'Standard meter reading taken from the physical meter'}
                                    {data.reading_type === 'estimated' && 'Estimated reading based on average consumption'}
                                    {data.reading_type === 'correction' && 'Correction of a previous reading error'}
                                </p>
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
                                    placeholder="Add any notes about this reading..."
                                />
                                {errors.notes && (
                                    <p className="text-sm text-red-600 mt-1">{errors.notes}</p>
                                )}
                            </div>

                            {valueChanged && (
                                <Alert>
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription>
                                        <strong>Warning:</strong> Changing the reading value will trigger 
                                        recalculation of consumption for this and subsequent readings. This 
                                        may affect existing bills. Consider using a correction reading type 
                                        if this is fixing an error.
                                    </AlertDescription>
                                </Alert>
                            )}

                            <div className="flex gap-2 justify-end">
                                <Link href={route('meter-readings.show', reading.id)}>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button 
                                    type="submit" 
                                    disabled={!can.update || processing}
                                >
                                    {processing ? 'Updating...' : 'Update Reading'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}