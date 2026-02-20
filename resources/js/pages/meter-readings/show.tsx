import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Pencil, Trash, Activity, Gauge, TrendingUp, Calendar } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface Meter {
    id: number;
    meter_number: string;
    meter_name: string;
}

interface PreviousReading {
    reading_value: number;
    reading_date: string;
}

interface NextReading {
    reading_value: number;
    reading_date: string;
}

interface MeterReading {
    id: number;
    meter_id: number;
    meter?: Meter;
    reading_date: string;
    reading_value: number;
    consumption?: number;
    reading_type: string;
    notes?: string;
    created_at: string;
    updated_at: string;
    created_by_name?: string;
    previous_reading?: PreviousReading;
    next_reading?: NextReading;
}

interface ReadingShowPageProps {
    reading: MeterReading;
    can: {
        update: boolean;
        delete: boolean;
    };
}

export default function MeterReadingShow() {
    const { reading, can } = usePage<SharedData & ReadingShowPageProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Meter Readings', href: route('meter-readings.index') },
        { title: `Reading #${reading.id}`, href: '#' },
    ];

    const readingTypeClasses: Record<string, string> = {
        regular: "bg-blue-400/20 text-blue-700",
        estimated: "bg-yellow-400/20 text-yellow-700",
        correction: "bg-purple-400/20 text-purple-700",
    };

    const handleDelete = () => {
        if (confirm("Are you sure you want to delete this reading? This action cannot be undone and may affect billing calculations.")) {
            router.delete(route('meter-readings.destroy', reading.id), {
                onSuccess: () => {
                    router.visit(route('meter-readings.index'));
                },
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Reading #${reading.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header */}
                <div className="flex justify-between items-start mb-4">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2">
                            <Activity className="h-6 w-6" />
                            Reading #{reading.id}
                        </h1>
                        <p className="text-gray-500 text-sm mt-1">
                            {new Date(reading.reading_date).toLocaleDateString('en-US', {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            })}
                        </p>
                    </div>
                    <div className="flex gap-2 flex-wrap justify-end">
                        {can.update && (
                            <Link href={route('meter-readings.edit', reading.id)}>
                                <Button variant="outline" size="sm">
                                    <Pencil size={16} className="mr-2" /> Edit
                                </Button>
                            </Link>
                        )}
                        {can.delete && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={handleDelete}
                            >
                                <Trash size={16} className="mr-2" /> Delete
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {/* Meter Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Gauge size={20} />
                                Meter
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {reading.meter ? (
                                <>
                                    <div>
                                        <p className="text-xs text-gray-500">Meter Number</p>
                                        <Link
                                            href={route('meters.show', reading.meter.id)}
                                            className="font-medium text-blue-600 hover:underline"
                                        >
                                            {reading.meter.meter_number}
                                        </Link>
                                    </div>
                                    <div>
                                        <p className="text-xs text-gray-500">Meter Name</p>
                                        <p className="font-medium">{reading.meter.meter_name}</p>
                                    </div>
                                </>
                            ) : (
                                <p className="text-sm text-gray-500">Meter information not available</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Reading Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp size={20} />
                                Reading
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Reading Value</p>
                                <p className="font-medium text-2xl">{reading.reading_value} m³</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Reading Type</p>
                                <Badge className={readingTypeClasses[reading.reading_type]}>
                                    {reading.reading_type}
                                </Badge>
                            </div>
                            {reading.consumption !== undefined && reading.consumption !== null && (
                                <div>
                                    <p className="text-xs text-gray-500">Consumption</p>
                                    <p className="font-medium text-lg text-green-600">
                                        {reading.consumption} m³
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Timeline */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Calendar size={20} />
                                Timeline
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Reading Date</p>
                                <p className="font-medium">
                                    {new Date(reading.reading_date).toLocaleDateString()}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Recorded On</p>
                                <p className="font-medium">
                                    {new Date(reading.created_at).toLocaleDateString()}
                                </p>
                            </div>
                            {reading.created_by_name && (
                                <div>
                                    <p className="text-xs text-gray-500">Recorded By</p>
                                    <p className="font-medium">{reading.created_by_name}</p>
                                </div>
                            )}
                            {reading.updated_at !== reading.created_at && (
                                <div>
                                    <p className="text-xs text-gray-500">Last Updated</p>
                                    <p className="font-medium">
                                        {new Date(reading.updated_at).toLocaleDateString()}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Reading Context */}
                {(reading.previous_reading || reading.next_reading) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Reading Context</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-3 gap-4">
                                {/* Previous Reading */}
                                <div className="p-4 rounded-md bg-gray-50 dark:bg-gray-900 border">
                                    <p className="text-xs text-gray-500 mb-2">Previous Reading</p>
                                    {reading.previous_reading ? (
                                        <>
                                            <p className="text-xl font-bold">
                                                {reading.previous_reading.reading_value} m³
                                            </p>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {new Date(reading.previous_reading.reading_date).toLocaleDateString()}
                                            </p>
                                        </>
                                    ) : (
                                        <p className="text-sm text-gray-400">First reading</p>
                                    )}
                                </div>

                                {/* Current Reading */}
                                <div className="p-4 rounded-md bg-blue-50 dark:bg-blue-950/20 border-2 border-blue-500">
                                    <p className="text-xs text-blue-600 dark:text-blue-400 mb-2 font-medium">
                                        Current Reading
                                    </p>
                                    <p className="text-xl font-bold text-blue-700 dark:text-blue-400">
                                        {reading.reading_value} m³
                                    </p>
                                    <p className="text-xs text-gray-500 mt-1">
                                        {new Date(reading.reading_date).toLocaleDateString()}
                                    </p>
                                </div>

                                {/* Next Reading */}
                                <div className="p-4 rounded-md bg-gray-50 dark:bg-gray-900 border">
                                    <p className="text-xs text-gray-500 mb-2">Next Reading</p>
                                    {reading.next_reading ? (
                                        <>
                                            <p className="text-xl font-bold">
                                                {reading.next_reading.reading_value} m³
                                            </p>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {new Date(reading.next_reading.reading_date).toLocaleDateString()}
                                            </p>
                                        </>
                                    ) : (
                                        <p className="text-sm text-gray-400">Latest reading</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Notes */}
                {reading.notes && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm whitespace-pre-wrap">{reading.notes}</p>
                        </CardContent>
                    </Card>
                )}

                {/* Reading Type Explanation */}
                <Card>
                    <CardHeader>
                        <CardTitle>About This Reading Type</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-sm text-gray-600 dark:text-gray-400">
                            {reading.reading_type === 'regular' && (
                                <p>
                                    This is a <strong>regular reading</strong> taken directly from the physical 
                                    meter. These readings are the most accurate and are used as the primary 
                                    source for billing calculations.
                                </p>
                            )}
                            {reading.reading_type === 'estimated' && (
                                <p>
                                    This is an <strong>estimated reading</strong> calculated based on average 
                                    consumption patterns. Estimated readings are used when physical access to 
                                    the meter is not possible and will be adjusted when actual readings are 
                                    taken.
                                </p>
                            )}
                            {reading.reading_type === 'correction' && (
                                <p>
                                    This is a <strong>correction reading</strong> used to fix errors in 
                                    previously recorded readings. Corrections ensure accurate consumption 
                                    tracking and billing adjustments.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}