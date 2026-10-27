import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, Layers } from 'lucide-react';

interface BulkMeter {
    id: number;
    meter_number: string;
    meter_name: string;
    account_id: number;
    account_name?: string;
    account_number?: string;
    current_reading?: number | null;
    last_reading_date?: string | null;
    has_reading_this_period: boolean;
}

interface BulkCreatePageProps {
    meters: BulkMeter[];
    current_month: string;
    can: {
        create: boolean;
        generate_bill?: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Meter Readings', href: route('meter-readings.index') },
    { title: 'Bulk readings', href: '#' },
];

export default function BulkCreateMeterReadings() {
    const { meters, current_month, can } = usePage<SharedData & BulkCreatePageProps>().props;

    const { data, setData, post, processing, errors } = useForm({
        reading_date: new Date().toISOString().split('T')[0],
        generate_bills: false as boolean,
        readings: meters.map((meter) => ({
            meter_id: meter.id,
            reading_value: '',
            notes: '',
        })),
    });

    const updateReading = (index: number, field: 'reading_value' | 'notes', value: string) => {
        const next = [...data.readings];
        next[index] = { ...next[index], [field]: value };
        setData('readings', next);
    };

    const filledCount = data.readings.filter((reading) => reading.reading_value !== '').length;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('meter-readings.bulk.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bulk meter readings" />

            <div className="mx-auto max-w-6xl py-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Layers className="h-6 w-6" />
                            <CardTitle>Bulk meter readings</CardTitle>
                        </div>
                        <CardDescription>
                            Enter readings for multiple meters at once for {current_month}. Leave a row blank to skip that meter.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!can.create && (
                            <Alert className="mb-4">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>You do not have permission to record meter readings.</AlertDescription>
                            </Alert>
                        )}

                        {meters.length === 0 ? (
                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>There are no active meters to record.</AlertDescription>
                            </Alert>
                        ) : (
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="reading_date">Reading date *</Label>
                                        <Input
                                            id="reading_date"
                                            type="date"
                                            value={data.reading_date}
                                            max={new Date().toISOString().split('T')[0]}
                                            onChange={(e) => setData('reading_date', e.target.value)}
                                            disabled={!can.create || processing}
                                            className="mt-1"
                                        />
                                        {errors.reading_date && <p className="mt-1 text-sm text-red-600">{errors.reading_date}</p>}
                                    </div>
                                    {can.generate_bill && (
                                        <div className="flex items-end pb-2">
                                            <label className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={data.generate_bills}
                                                    onCheckedChange={(checked) => setData('generate_bills', checked === true)}
                                                    disabled={processing}
                                                />
                                                Also generate bills for these accounts
                                            </label>
                                        </div>
                                    )}
                                </div>

                                <div className="overflow-x-auto rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Meter</TableHead>
                                                <TableHead>Account</TableHead>
                                                <TableHead>Last reading</TableHead>
                                                <TableHead>New reading (m³)</TableHead>
                                                <TableHead>Notes</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {meters.map((meter, index) => (
                                                <TableRow key={meter.id} className={meter.has_reading_this_period ? 'opacity-70' : undefined}>
                                                    <TableCell>
                                                        <p className="font-medium">{meter.meter_number}</p>
                                                        <p className="text-xs text-muted-foreground">{meter.meter_name}</p>
                                                        {meter.has_reading_this_period && (
                                                            <p className="text-xs text-amber-600">Already read this month</p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-sm">
                                                        {meter.account_name}
                                                        {meter.account_number ? ` (${meter.account_number})` : ''}
                                                    </TableCell>
                                                    <TableCell className="text-sm">
                                                        {meter.current_reading !== undefined && meter.current_reading !== null
                                                            ? `${meter.current_reading} m³`
                                                            : '—'}
                                                        {meter.last_reading_date && (
                                                            <p className="text-xs text-muted-foreground">
                                                                {new Date(meter.last_reading_date).toLocaleDateString()}
                                                            </p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            value={data.readings[index]?.reading_value ?? ''}
                                                            onChange={(e) => updateReading(index, 'reading_value', e.target.value)}
                                                            disabled={!can.create || processing}
                                                            placeholder="Skip if empty"
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            value={data.readings[index]?.notes ?? ''}
                                                            onChange={(e) => updateReading(index, 'notes', e.target.value)}
                                                            disabled={!can.create || processing}
                                                            placeholder="Optional"
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>

                                <div className="flex items-center justify-between">
                                    <p className="text-sm text-muted-foreground">{filledCount} of {meters.length} meters filled</p>
                                    <div className="flex gap-2">
                                        <Link href={route('meter-readings.index')}>
                                            <Button type="button" variant="outline">
                                                Cancel
                                            </Button>
                                        </Link>
                                        <Button type="submit" disabled={!can.create || processing || filledCount === 0}>
                                            {processing ? 'Saving...' : `Save ${filledCount} reading${filledCount === 1 ? '' : 's'}`}
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
