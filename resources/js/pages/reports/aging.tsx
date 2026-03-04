import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { formatCurrency } from '@/lib/utils';
import { Clock } from 'lucide-react';

interface Bucket { label: string; amount: number; count: number; }
interface AccountRow {
    account_id: number; account_name: string; account_number: string; phone: string;
    current: number; '1_30': number; '31_60': number; '61_90': number; '90_plus': number; total: number;
}

interface AgingProps { buckets: Bucket[]; accounts: AccountRow[]; grand_total: number; generated_at: string; }

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: route('reports.index') },
    { title: 'AR Aging', href: '#' },
];

const BUCKET_COLORS = [
    'text-green-600 bg-green-50 dark:bg-green-950/30',
    'text-yellow-600 bg-yellow-50 dark:bg-yellow-950/30',
    'text-orange-600 bg-orange-50 dark:bg-orange-950/30',
    'text-red-600 bg-red-50 dark:bg-red-950/30',
    'text-red-800 bg-red-100 dark:bg-red-900/40',
];

export default function AgingReport() {
    const { buckets, accounts, grand_total, generated_at } = usePage<SharedData & AgingProps>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AR Aging Report" />
            <div className="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2"><Clock size={22} className="text-red-600" />Accounts Receivable Aging</h1>
                        <p className="text-sm text-muted-foreground">Generated {generated_at} · Total outstanding: {formatCurrency(grand_total)}</p>
                    </div>
                </div>

                {/* Bucket summary */}
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    {buckets.map((bucket, i) => (
                        <Card key={bucket.label}>
                            <CardContent className="pt-4 pb-4">
                                <div className={`inline-flex items-center justify-center w-8 h-8 rounded-full mb-2 text-xs font-bold ${BUCKET_COLORS[i]}`}>
                                    {i === 0 ? '✓' : `${[30,60,90,'90+'][i-1]}`}
                                </div>
                                <p className="text-xs text-muted-foreground">{bucket.label}</p>
                                <p className="font-semibold mt-1">{formatCurrency(bucket.amount)}</p>
                                <p className="text-xs text-muted-foreground">{bucket.count} bill{bucket.count !== 1 ? 's' : ''}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Detail table */}
                <Card>
                    <CardHeader><CardTitle>Account Detail</CardTitle></CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Account</TableHead>
                                        <TableHead>Phone</TableHead>
                                        <TableHead className="text-right text-green-600">Current</TableHead>
                                        <TableHead className="text-right text-yellow-600">1–30 days</TableHead>
                                        <TableHead className="text-right text-orange-600">31–60 days</TableHead>
                                        <TableHead className="text-right text-red-600">61–90 days</TableHead>
                                        <TableHead className="text-right text-red-800">90+ days</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {accounts.length === 0 ? (
                                        <TableRow><TableCell colSpan={8} className="text-center text-muted-foreground py-8">No outstanding balances.</TableCell></TableRow>
                                    ) : accounts.map((acc) => (
                                        <TableRow key={acc.account_id}>
                                            <TableCell>
                                                <p className="font-medium text-sm">{acc.account_name}</p>
                                                <p className="text-xs text-muted-foreground">{acc.account_number}</p>
                                            </TableCell>
                                            <TableCell className="text-xs">{acc.phone || '—'}</TableCell>
                                            <TableCell className="text-right text-xs">{acc.current > 0 ? formatCurrency(acc.current) : '—'}</TableCell>
                                            <TableCell className="text-right text-xs">{acc['1_30'] > 0 ? formatCurrency(acc['1_30']) : '—'}</TableCell>
                                            <TableCell className="text-right text-xs">{acc['31_60'] > 0 ? formatCurrency(acc['31_60']) : '—'}</TableCell>
                                            <TableCell className="text-right text-xs">{acc['61_90'] > 0 ? formatCurrency(acc['61_90']) : '—'}</TableCell>
                                            <TableCell className="text-right text-xs font-medium text-red-600">{acc['90_plus'] > 0 ? formatCurrency(acc['90_plus']) : '—'}</TableCell>
                                            <TableCell className="text-right text-sm font-bold">{formatCurrency(acc.total)}</TableCell>
                                        </TableRow>
                                    ))}
                                    {accounts.length > 0 && (
                                        <TableRow className="border-t-2 font-bold bg-muted/30">
                                            <TableCell colSpan={2}>Grand Total</TableCell>
                                            {(['current','1_30','31_60','61_90','90_plus'] as const).map((k) => (
                                                <TableCell key={k} className="text-right text-sm">
                                                    {formatCurrency(accounts.reduce((s, a) => s + a[k], 0))}
                                                </TableCell>
                                            ))}
                                            <TableCell className="text-right text-sm">{formatCurrency(grand_total)}</TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}