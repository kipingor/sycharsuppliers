import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency } from '@/lib/utils';

interface StatementProps extends PageProps {
    meter: {
        id: number;
        meter_number: string;
        meter_name: string;
        resident?: {
            name: string;
            email?: string;
        };
        meterReadings: Array<{
            reading_value: number;
            reading_date: string;
        }>;
        payments: Array<{
            payment_date: string;
            amount: number;
            method: string;
        }>;
        bills: Array<{
            id: number; // Added id property
            amount: string;
            status: number;
            current_reading: number;
            previous_reading: number;
        }>;
    };
    totalDue: number;
    totalPaid: number;
    balance: number;
}

export default function Statement({ meter, totalDue, totalPaid, balance }: StatementProps) {
    return (
        <>
            <Head title="Billing Statement" />
            <div className="container mx-auto py-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Billing Statement</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-6">
                            <h2 className="text-lg font-semibold mb-2">Account Information</h2>
                            <p>Meter Number: {meter.meter_number}</p>
                            <p>Meter Name: {meter.meter_name}</p>
                            <p>Resident: {meter.resident?.name || 'N/A'}</p>
                        </div>

                        <div className="mb-6">
                            <h2 className="text-lg font-semibold mb-2">Summary</h2>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">Total Due</p>
                                    <p className="text-lg font-semibold">{formatCurrency(totalDue)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Total Paid</p>
                                    <p className="text-lg font-semibold">{formatCurrency(totalPaid)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Balance</p>
                                    <p className="text-lg font-semibold">{formatCurrency(balance)}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h2 className="text-lg font-semibold mb-2">Billing History</h2>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {meter.bills.map((bill) => (
                                        <TableRow key={bill.id}>
                                            <TableCell>{formatCurrency(Number(bill.amount))}</TableCell> {/* Fixed: Convert string to number for formatCurrency */}
                                            <TableCell>{bill.status}</TableCell>
                                            
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
} 