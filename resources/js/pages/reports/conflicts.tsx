import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/table';
import { formatCurrency } from '@/lib/utils';
import { AlertTriangle } from 'lucide-react';

interface Issue {
    code: string;
    label: string;
    count: number;
    amount: number;
    bill_ids: number[];
}

interface ConflictAccount {
    account_id: number;
    account_number: string;
    name: string;
    status: string;
    ledger_balance: number;
    open_bill_balance: number;
    difference: number;
    issues: Issue[];
}

interface Props {
    accounts: ConflictAccount[];
    total_accounts: number;
    generated_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: route('reports.index') },
    { title: 'Account Conflicts', href: '#' },
];

export default function AccountConflictsReport() {
    const { accounts, total_accounts, generated_at } = usePage<SharedData & Props>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Account Conflicts" />
            <div className="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-bold flex items-center gap-2">
                        <AlertTriangle size={22} className="text-amber-600" />
                        Account Conflict Report
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        {total_accounts} account(s) flagged · Generated {generated_at}
                    </p>
                    <p className="text-sm text-muted-foreground mt-1">
                        Flags indicate records to investigate, not automatic proof of incorrect accounting. Verify source receipts and approvals before editing.
                    </p>
                </div>

                <Card>
                    <CardContent className="pt-4">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Account</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Ledger balance</TableHead>
                                        <TableHead className="text-right">Open bills</TableHead>
                                        <TableHead className="text-right">Difference</TableHead>
                                        <TableHead>Conflicts to review</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {accounts.length === 0 ? (
                                        <TableRow><TableCell colSpan={6} className="py-10 text-center text-muted-foreground">No account conflicts found.</TableCell></TableRow>
                                    ) : accounts.map((account) => (
                                        <TableRow key={account.account_id} className="align-top">
                                            <TableCell>
                                                <Link href={route('accounts.show', account.account_id)} className="font-medium hover:underline">
                                                    {account.name}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">{account.account_number}</p>
                                            </TableCell>
                                            <TableCell><Badge variant="outline" className="capitalize">{account.status}</Badge></TableCell>
                                            <TableCell className="text-right">{formatCurrency(account.ledger_balance)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(account.open_bill_balance)}</TableCell>
                                            <TableCell className="text-right font-semibold">{formatCurrency(account.difference)}</TableCell>
                                            <TableCell className="min-w-[320px]">
                                                <ul className="space-y-2">
                                                    {account.issues.map((issue) => (
                                                        <li key={issue.code} className="text-sm">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <Badge variant="destructive">{issue.count}</Badge>
                                                                <span>{issue.label}</span>
                                                                {issue.amount > 0 && <span className="text-muted-foreground">{formatCurrency(issue.amount)}</span>}
                                                            </div>
                                                            {issue.bill_ids.length > 0 && (
                                                                <p className="mt-1 text-xs text-muted-foreground">
                                                                    Bills:{' '}
                                                                    {issue.bill_ids.map((id, index) => (
                                                                        <span key={id}>
                                                                            {index > 0 && ', '}
                                                                            <Link href={route('billings.show', id)} className="underline">#{id}</Link>
                                                                        </span>
                                                                    ))}
                                                                </p>
                                                            )}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
