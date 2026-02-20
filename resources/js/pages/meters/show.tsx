import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Pencil, Trash, Gauge, TrendingUp, Activity, Receipt, FileText } from 'lucide-react';
import { formatCurrency } from '@/lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { Badge } from '@/components/ui/badge';

interface Account {
    id: number;
    name: string;
    account_number?: string;
}

interface BulkMeter {
    id: number;
    meter_number: string;
    meter_name: string;
}

interface MeterReading {
    id: number;
    reading_date: string;
    reading_value: number;
    consumption?: number;
    reading_type: string;
    notes?: string;
}

interface Bill {
    id: number;
    billing_period: string;
    total_amount: number;
    status: string;
    due_date: string;
}

interface Meter {
    id: number;
    meter_number: string;
    meter_name: string;
    meter_type: 'individual' | 'bulk';
    account_id?: number;
    account?: Account;
    bulk_meter_id?: number;
    bulk_meter?: BulkMeter;
    status: string;
    location?: string;
    installation_date?: string;
    initial_reading: number;
    current_reading?: number;
    total_consumption?: number;
    last_reading_date?: string;
    notes?: string;
    created_at: string;
}

interface MeterShowPageProps {
    meter: Meter;
    readings: MeterReading[];
    bills: Bill[];
    can: {
        update: boolean;
        delete: boolean;
        createReading?: boolean;
    };
}

export default function MeterShow() {
    const { meter, readings, bills, can } = usePage<SharedData & MeterShowPageProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Meters', href: route('meters.index') },
        { title: meter.meter_number, href: '#' },
    ];

    const statusClasses: Record<string, string> = {
        active: "bg-green-400/20 text-green-700 dark:bg-green-400/10 dark:text-green-300",
        inactive: "bg-gray-400/20 text-gray-700 dark:bg-gray-400/10 dark:text-gray-300",
        maintenance: "bg-yellow-400/20 text-yellow-700 dark:bg-yellow-400/10 dark:text-yellow-300",
        faulty: "bg-red-400/20 text-red-700 dark:bg-red-400/10 dark:text-red-300",
    };

    const billStatusClasses: Record<string, string> = {
        paid: "bg-lime-400/20 text-lime-700",
        pending: "bg-sky-400/15 text-sky-700",
        overdue: "bg-pink-400/15 text-pink-700",
        partially_paid: "bg-blue-400/15 text-blue-700",
    };

    const handleDelete = () => {
        if (confirm("Are you sure you want to delete this meter? This will also delete all associated readings and billing data. This action cannot be undone.")) {
            router.delete(route('meters.destroy', meter.id), {
                onSuccess: () => {
                    router.visit(route('meters.index'));
                },
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Meter ${meter.meter_number}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header */}
                <div className="flex justify-between items-start mb-4">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2">
                            <Gauge className="h-6 w-6" />
                            {meter.meter_number}
                        </h1>
                        <p className="text-gray-500 text-sm mt-1">{meter.meter_name}</p>
                    </div>
                    <div className="flex gap-2 flex-wrap justify-end">
                        {can.createReading && (
                            <Link href={route('meter-readings.create', { meter_id: meter.id })}>
                                <Button variant="default" size="sm">
                                    <Activity size={16} className="mr-2" /> Record Reading
                                </Button>
                            </Link>
                        )}
                        {can.update && (
                            <Link href={route('meters.edit', meter.id)}>
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

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    {/* Meter Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Meter Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Type</p>
                                <p className="font-medium capitalize">{meter.meter_type}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Status</p>
                                <Badge className={statusClasses[meter.status] || statusClasses.active}>
                                    {meter.status}
                                </Badge>
                            </div>
                            {meter.location && (
                                <div>
                                    <p className="text-xs text-gray-500">Location</p>
                                    <p className="font-medium text-sm">{meter.location}</p>
                                </div>
                            )}
                            {meter.installation_date && (
                                <div>
                                    <p className="text-xs text-gray-500">Installed</p>
                                    <p className="font-medium">
                                        {new Date(meter.installation_date).toLocaleDateString()}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Account Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Account</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {meter.account ? (
                                <>
                                    <div>
                                        <p className="text-xs text-gray-500">Account Name</p>
                                        <Link 
                                            href={route('residents.show', meter.account.id)}
                                            className="font-medium text-blue-600 hover:underline"
                                        >
                                            {meter.account.name}
                                        </Link>
                                    </div>
                                    <div>
                                        <p className="text-xs text-gray-500">Account Number</p>
                                        <p className="font-medium">{meter.account.account_number || 'N/A'}</p>
                                    </div>
                                </>
                            ) : (
                                <p className="text-sm text-gray-500">No account assigned</p>
                            )}
                            {meter.bulk_meter && (
                                <div>
                                    <p className="text-xs text-gray-500">Parent Bulk Meter</p>
                                    <Link
                                        href={route('meters.show', meter.bulk_meter.id)}
                                        className="font-medium text-sm text-blue-600 hover:underline"
                                    >
                                        {meter.bulk_meter.meter_number}
                                    </Link>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Reading Statistics */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp size={18} />
                                Readings
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Initial Reading</p>
                                <p className="font-medium text-lg">{meter.initial_reading} m³</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Current Reading</p>
                                <p className="font-medium text-lg">
                                    {meter.current_reading !== undefined ? `${meter.current_reading} m³` : 'N/A'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Total Consumption</p>
                                <p className="font-medium text-lg text-blue-600">
                                    {meter.total_consumption !== undefined ? `${meter.total_consumption} m³` : 'N/A'}
                                </p>
                            </div>
                            {meter.last_reading_date && (
                                <div>
                                    <p className="text-xs text-gray-500">Last Reading</p>
                                    <p className="font-medium text-sm">
                                        {new Date(meter.last_reading_date).toLocaleDateString()}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Billing Summary */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Receipt size={18} />
                                Billing
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div>
                                <p className="text-xs text-gray-500">Total Bills</p>
                                <p className="font-medium text-lg">{bills.length}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Paid Bills</p>
                                <p className="font-medium text-green-600">
                                    {bills.filter(b => b.status === 'paid').length}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Outstanding Bills</p>
                                <p className="font-medium text-red-600">
                                    {bills.filter(b => ['pending', 'overdue', 'partially_paid'].includes(b.status)).length}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Readings */}
                <Card>
                    <CardHeader>
                        <div className="flex justify-between items-center">
                            <CardTitle className="flex items-center gap-2">
                                <Activity size={20} />
                                Recent Readings
                            </CardTitle>
                            {can.createReading && (
                                <Link href={route('meter-readings.create', { meter_id: meter.id })}>
                                    <Button variant="outline" size="sm">
                                        Record Reading
                                    </Button>
                                </Link>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {readings && readings.length > 0 ? (
                            <Table>
                                <TableHead>
                                    <TableRow>
                                        <TableCell>Date</TableCell>
                                        <TableCell className="text-right">Reading</TableCell>
                                        <TableCell className="text-right">Consumption</TableCell>
                                        <TableCell>Type</TableCell>
                                        <TableCell>Notes</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {readings.slice(0, 10).map((reading) => (
                                        <TableRow key={reading.id}>
                                            <TableCell>
                                                {new Date(reading.reading_date).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {reading.reading_value} m³
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {reading.consumption !== undefined 
                                                    ? `${reading.consumption} m³` 
                                                    : '-'}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                <Badge variant="outline">{reading.reading_type}</Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-gray-500">
                                                {reading.notes || '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-center py-8">
                                <p className="text-gray-500">No readings recorded yet.</p>
                                {can.createReading && (
                                    <Link href={route('meter-readings.create', { meter_id: meter.id })}>
                                        <Button className="mt-4">Record First Reading</Button>
                                    </Link>
                                )}
                            </div>
                        )}
                        {readings && readings.length > 10 && (
                            <div className="mt-4 text-center">
                                <Link href={route('meter-readings.index', { meter_id: meter.id })}>
                                    <Button variant="link">View All Readings</Button>
                                </Link>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Recent Bills */}
                <Card>
                    <CardHeader>
                        <div className="flex justify-between items-center">
                            <CardTitle className="flex items-center gap-2">
                                <FileText size={20} />
                                Recent Bills
                            </CardTitle>
                            <Link href={route('billings.index', { meter_id: meter.id })}>
                                <Button variant="outline" size="sm">
                                    View All Bills
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {bills && bills.length > 0 ? (
                            <Table>
                                <TableHead>
                                    <TableRow>
                                        <TableCell>Bill #</TableCell>
                                        <TableCell>Period</TableCell>
                                        <TableCell className="text-right">Amount</TableCell>
                                        <TableCell>Due Date</TableCell>
                                        <TableCell>Status</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {bills.slice(0, 5).map((bill) => (
                                        <TableRow key={bill.id}>
                                            <TableCell>
                                                <Link
                                                    href={route('billings.show', bill.id)}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    #{bill.id}
                                                </Link>
                                            </TableCell>
                                            <TableCell>{bill.billing_period}</TableCell>
                                            <TableCell className="text-right font-medium">
                                                {formatCurrency(bill.total_amount)}
                                            </TableCell>
                                            <TableCell>
                                                {new Date(bill.due_date).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <Badge className={billStatusClasses[bill.status]}>
                                                    {bill.status.replace('_', ' ')}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-center py-8">
                                <p className="text-gray-500">No bills generated yet.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Notes */}
                {meter.notes && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm whitespace-pre-wrap">{meter.notes}</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}