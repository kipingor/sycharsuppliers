import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ArrowLeft, User, Mail, Phone, MapPin, AlertTriangle } from 'lucide-react';

interface Account {
    id: number;
    account_number: string;
    name: string;
    email?: string;
    phone?: string;
    address?: string;
    status: 'active' | 'suspended' | 'inactive';
}

interface EditAccountProps {
    account: Account;
}

export default function EditAccount({ account }: EditAccountProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Accounts', href: route('accounts.index') },
        { title: account.name, href: route('accounts.show', account.id) },
        { title: 'Edit', href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: account.name,
        email: account.email ?? '',
        phone: account.phone ?? '',
        address: account.address ?? '',
        status: account.status,
    });

    const statusChanging = data.status !== account.status;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('accounts.update', account.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Account — ${account.name}`} />
            <div className="max-w-2xl mx-auto py-6 px-4">
                <div className="flex items-center gap-3 mb-6">
                    <Link href={route('accounts.show', account.id)}>
                        <Button variant="ghost" size="sm">
                            <ArrowLeft size={16} className="mr-1" /> Back
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold">Edit Account</h1>
                        <p className="text-sm text-muted-foreground mt-0.5 font-mono">{account.account_number}</p>
                    </div>
                </div>

                {statusChanging && (
                    <Alert className="mb-4 border-yellow-300 bg-yellow-50 dark:bg-yellow-950/20">
                        <AlertTriangle className="h-4 w-4 text-yellow-600" />
                        <AlertDescription className="text-yellow-700 dark:text-yellow-400">
                            You are changing the account status from <strong>{account.status}</strong> to <strong>{data.status}</strong>. 
                            {data.status === 'suspended' && ' Suspended accounts cannot be billed or make payments.'}
                            {data.status === 'active' && ' Re-activating the account will allow billing and payments.'}
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Account Details</CardTitle>
                        <CardDescription>Update the account information. Account number cannot be changed.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-5">
                            {/* Account Number (read-only) */}
                            <div>
                                <Label className="text-muted-foreground mb-1.5 block">Account Number (Auto-generated)</Label>
                                <Input value={account.account_number} disabled className="bg-muted font-mono" />
                            </div>

                            {/* Name */}
                            <div>
                                <Label htmlFor="name" className="flex items-center gap-1.5 mb-1.5">
                                    <User size={14} /> Account Name *
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    placeholder="e.g. John Doe or Unit 12A"
                                />
                                {errors.name && <p className="text-sm text-destructive mt-1">{errors.name}</p>}
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {/* Email */}
                                <div>
                                    <Label htmlFor="email" className="flex items-center gap-1.5 mb-1.5">
                                        <Mail size={14} /> Email
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={e => setData('email', e.target.value)}
                                        placeholder="email@example.com"
                                    />
                                    {errors.email && <p className="text-sm text-destructive mt-1">{errors.email}</p>}
                                </div>

                                {/* Phone */}
                                <div>
                                    <Label htmlFor="phone" className="flex items-center gap-1.5 mb-1.5">
                                        <Phone size={14} /> Phone
                                    </Label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={data.phone}
                                        onChange={e => setData('phone', e.target.value)}
                                        placeholder="+254 712 345 678"
                                    />
                                    {errors.phone && <p className="text-sm text-destructive mt-1">{errors.phone}</p>}
                                </div>
                            </div>

                            {/* Address */}
                            <div>
                                <Label htmlFor="address" className="flex items-center gap-1.5 mb-1.5">
                                    <MapPin size={14} /> Address
                                </Label>
                                <Textarea
                                    id="address"
                                    value={data.address}
                                    onChange={e => setData('address', e.target.value)}
                                    placeholder="Physical address or unit description"
                                    rows={3}
                                />
                                {errors.address && <p className="text-sm text-destructive mt-1">{errors.address}</p>}
                            </div>

                            {/* Status */}
                            <div>
                                <Label className="mb-1.5 block">Status *</Label>
                                <Select value={data.status} onValueChange={v => setData('status', v)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="suspended">Suspended</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.status && <p className="text-sm text-destructive mt-1">{errors.status}</p>}
                            </div>

                            {/* Actions */}
                            <div className="flex items-center justify-between pt-2">
                                <Link href={route('accounts.show', account.id)}>
                                    <Button type="button" variant="outline">Cancel</Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving…' : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}