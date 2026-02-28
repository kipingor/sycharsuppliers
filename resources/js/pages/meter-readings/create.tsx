import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Activity, AlertCircle, CheckCircle, TrendingUp } from 'lucide-react';

interface Meter {
    id: number;
    meter_number: string;
    meter_name: string;
    current_reading?: number;
    last_reading_date?: string;
}

interface CreateReadingPageProps {
    meters: Meter[];
    current_month: string;
    can: {
        create: boolean;
    };
    meter_id?: number; // Pre-selected meter from query param
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Meter Readings', href: route('meter-readings.index') },
    { title: 'Record Reading', href: '#' },
];

const readingTypes = [
    { value: 'actual', label: 'Actual Reading' },
    { value: 'estimated', label: 'Estimated Reading' },
    { value: 'correction', label: 'Correction' },
];

export default function CreateMeterReading() {
    const { meters, can, meter_id, current_month } = usePage<SharedData & CreateReadingPageProps>().props;

    const { data, setData, post, processing, errors } = useForm({
        meter_id: meter_id?.toString() || '',
        reading_date: new Date().toISOString().split('T')[0],
        reading_value: '',
        reading_type: 'actual',
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('meter-readings.store'));
    };

    const selectedMeter = meters.find((m) => m.id === Number(data.meter_id));
    const estimatedConsumption =
        selectedMeter?.current_reading && data.reading_value ? Number(data.reading_value) - selectedMeter.current_reading : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Record Reading" />

            <div className="mx-auto max-w-3xl py-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Activity className="h-6 w-6" />
                            <CardTitle>Record Meter Reading</CardTitle>
                        </div>
                        <CardDescription>Record a new water meter reading for consumption tracking and billing.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!can.create && (
                            <Alert className="mb-4">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>You do not have permission to record meter readings.</AlertDescription>
                            </Alert>
                        )}

                        {meters.length === 0 && (
                            <Alert variant="default" className="mb-4">
                                <CheckCircle className="h-4 w-4" />
                                <AlertDescription>All meters have been read for {current_month}. Great work!</AlertDescription>
                            </Alert>
                        )}
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Meter Selection */}
                            <div>
                                <Label htmlFor="meter_id">Meter *</Label>
                                <Select
                                    value={data.meter_id}
                                    onValueChange={(value) => setData('meter_id', value)}
                                    disabled={!can.create || processing}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select meter" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {meters.map((meter) => (
                                            <SelectItem key={meter.id} value={meter.id.toString()}>
                                                <div className="flex flex-col">
                                                    <span className="font-medium">
                                                        {meter.meter_number} - {meter.meter_name}
                                                    </span>
                                                    {meter.current_reading !== undefined && (
                                                        <span className="text-xs text-gray-500">
                                                            Current: {meter.current_reading} m³
                                                            {meter.last_reading_date && (
                                                                <> ({new Date(meter.last_reading_date).toLocaleDateString()})</>
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.meter_id && <p className="mt-1 text-sm text-red-600">{errors.meter_id}</p>}
                            </div>

                            {/* Current Meter Info */}
                            {selectedMeter && (
                                <div className="rounded-md border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/20">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-xs text-gray-600 dark:text-gray-400">Current Reading</p>
                                            <p className="text-lg font-bold text-blue-700 dark:text-blue-400">
                                                {selectedMeter.current_reading !== undefined
                                                    ? `${selectedMeter.current_reading} m³`
                                                    : 'No previous reading'}
                                            </p>
                                        </div>
                                        {selectedMeter.last_reading_date && (
                                            <div>
                                                <p className="text-xs text-gray-600 dark:text-gray-400">Last Reading Date</p>
                                                <p className="text-lg font-medium">
                                                    {new Date(selectedMeter.last_reading_date).toLocaleDateString()}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                {/* Reading Date */}
                                <div>
                                    <Label htmlFor="reading_date">Reading Date *</Label>
                                    <Input
                                        id="reading_date"
                                        type="date"
                                        value={data.reading_date}
                                        onChange={(e) => setData('reading_date', e.target.value)}
                                        disabled={!can.create || processing}
                                        className="mt-1"
                                        max={new Date().toISOString().split('T')[0]}
                                    />
                                    {errors.reading_date && <p className="mt-1 text-sm text-red-600">{errors.reading_date}</p>}
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
                                            disabled={!can.create || processing}
                                            placeholder="0.00"
                                        />
                                    </div>
                                    {errors.reading_value && <p className="mt-1 text-sm text-red-600">{errors.reading_value}</p>}
                                    {selectedMeter?.current_reading !== undefined && data.reading_value && (
                                        <p className="mt-1 text-sm text-gray-500">Must be ≥ {selectedMeter.current_reading} m³</p>
                                    )}
                                </div>
                            </div>

                            {/* Estimated Consumption Preview */}
                            {estimatedConsumption !== null && (
                                <div className="rounded-md border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/20">
                                    <div className="flex items-center gap-2">
                                        <TrendingUp className="h-5 w-5 text-green-600" />
                                        <div>
                                            <p className="text-xs text-gray-600 dark:text-gray-400">Estimated Consumption</p>
                                            <p className="text-2xl font-bold text-green-700 dark:text-green-400">
                                                {estimatedConsumption.toFixed(2)} m³
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Reading Type */}
                            <div>
                                <Label htmlFor="reading_type">Reading Type *</Label>
                                <Select
                                    value={data.reading_type}
                                    onValueChange={(value) => setData('reading_type', value)}
                                    disabled={!can.create || processing}
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
                                {errors.reading_type && <p className="mt-1 text-sm text-red-600">{errors.reading_type}</p>}
                                <p className="mt-1 text-sm text-gray-500">
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
                                    disabled={!can.create || processing}
                                    className="border-input bg-background mt-1 min-h-[100px] w-full rounded-md border px-3 py-2 text-sm"
                                    placeholder="Add any notes about this reading (e.g., meter condition, access issues, unusual consumption)"
                                />
                                {errors.notes && <p className="mt-1 text-sm text-red-600">{errors.notes}</p>}
                            </div>

                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    Ensure the reading value is accurate. This will be used for consumption calculation and billing. The reading
                                    cannot be less than the previous reading.
                                </AlertDescription>
                            </Alert>

                            <div className="flex justify-end gap-2">
                                <Link href={route('meter-readings.index')}>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={!can.create || processing}>
                                    {processing ? 'Recording...' : 'Record Reading'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
