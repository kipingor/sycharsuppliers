import { AlertCircle, Gauge } from 'lucide-react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem, type SharedData } from '@/types';

interface Meter {
    id: number;
    meter_number: string;
    meter_name: string;
    meter_type: 'individual' | 'bulk';
    type: 'water' | 'sewer';
    status: string;
    location: string | null;
    account: {
        name: string;
        account_number: string;
    } | null;
    current_reading: number | null;
}

interface ReplaceMeterPageProps {
    meter: Meter;
}

export default function ReplaceMeter() {
    const { meter, errors: pageErrors } = usePage<SharedData & ReplaceMeterPageProps>().props;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Meters', href: route('meters.index') },
        { title: meter.meter_number, href: route('meters.show', meter.id) },
        { title: 'Replace', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        meter_number: '',
        meter_name: meter.meter_name,
        location: meter.location ?? '',
        final_reading: meter.current_reading?.toString() ?? '',
        initial_reading: '0',
        notes: '',
    });
    const meterError = pageErrors?.meter;
    const meterErrorText = Array.isArray(meterError) ? meterError[0] : meterError;

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('meters.replace', meter.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Replace Meter ${meter.meter_number}`} />
            <div className="mx-auto max-w-3xl py-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Gauge className="h-6 w-6" />
                            <CardTitle>Replace Faulty Meter</CardTitle>
                        </div>
                        <CardDescription>
                            Replace {meter.meter_number} for {meter.account?.name ?? 'its account'}.
                            The replacement keeps the same account, resident, service, and meter configuration.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Alert className="mb-6">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                Existing readings and bills will stay with the faulty meter. The final and initial
                                readings are recorded separately on the two meters.
                            </AlertDescription>
                        </Alert>

                        {meterErrorText && (
                            <Alert className="mb-4" variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>{meterErrorText}</AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="rounded-md border p-4 text-sm">
                                <p className="font-medium">{meter.meter_number} · {meter.meter_name}</p>
                                <p className="text-muted-foreground">
                                    {meter.account?.account_number ?? 'No account number'} · {meter.meter_type} · {meter.type}
                                </p>
                                {meter.current_reading !== null && (
                                    <p className="mt-2 text-muted-foreground">
                                        Latest recorded reading: {meter.current_reading} m³
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="meter_number">Replacement meter number *</Label>
                                    <Input
                                        id="meter_number"
                                        value={data.meter_number}
                                        onChange={(event) => setData('meter_number', event.target.value)}
                                        disabled={processing}
                                        className="mt-1"
                                        autoFocus
                                    />
                                    {errors.meter_number && (
                                        <p className="mt-1 text-sm text-red-600">{errors.meter_number}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="meter_name">Meter name *</Label>
                                    <Input
                                        id="meter_name"
                                        value={data.meter_name}
                                        onChange={(event) => setData('meter_name', event.target.value)}
                                        disabled={processing}
                                        className="mt-1"
                                    />
                                    {errors.meter_name && (
                                        <p className="mt-1 text-sm text-red-600">{errors.meter_name}</p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="location">Location *</Label>
                                <Input
                                    id="location"
                                    value={data.location}
                                    onChange={(event) => setData('location', event.target.value)}
                                    disabled={processing}
                                    className="mt-1"
                                />
                                {errors.location && (
                                    <p className="mt-1 text-sm text-red-600">{errors.location}</p>
                                )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="final_reading">Faulty meter final reading *</Label>
                                    <Input
                                        id="final_reading"
                                        type="number"
                                        min="0"
                                        max="999999"
                                        step="0.01"
                                        value={data.final_reading}
                                        onChange={(event) => setData('final_reading', event.target.value)}
                                        disabled={processing}
                                        className="mt-1"
                                    />
                                    {errors.final_reading && (
                                        <p className="mt-1 text-sm text-red-600">{errors.final_reading}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="initial_reading">Replacement meter initial reading *</Label>
                                    <Input
                                        id="initial_reading"
                                        type="number"
                                        min="0"
                                        max="999999"
                                        step="0.01"
                                        value={data.initial_reading}
                                        onChange={(event) => setData('initial_reading', event.target.value)}
                                        disabled={processing}
                                        className="mt-1"
                                    />
                                    {errors.initial_reading && (
                                        <p className="mt-1 text-sm text-red-600">{errors.initial_reading}</p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="notes">Replacement notes</Label>
                                <textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(event) => setData('notes', event.target.value)}
                                    disabled={processing}
                                    className="mt-1 min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                />
                                {errors.notes && (
                                    <p className="mt-1 text-sm text-red-600">{errors.notes}</p>
                                )}
                            </div>

                            <div className="flex justify-end gap-2">
                                <Link href={route('meters.show', meter.id)}>
                                    <Button type="button" variant="outline">Cancel</Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Replacing...' : 'Replace Meter'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
