import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, User, Mail, Phone, MapPin } from 'lucide-react';
import { Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounts', href: '/accounts' },
    { title: 'Create Account', href: '#' },
];

export default function CreateAccount() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        address: '',
        status: 'active',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('accounts.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Account" />
            <div className="max-w-2xl mx-auto py-6 px-4">
                <div className="flex items-center gap-3 mb-6">
                    <Link href={route('accounts.index')}>
                        <Button variant="ghost" size="sm">
                            <ArrowLeft size={16} className="mr-1" /> Back
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold">Create Account</h1>
                        <p className="text-sm text-muted-foreground mt-0.5">Add a new billing account</p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Account Details</CardTitle>
                        <CardDescription>Fill in the details for the new billing account. An account number will be auto-generated.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-5">
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
                                    autoFocus
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
                                        <SelectValue placeholder="Select status" />
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
                            <div className="flex items-center justify-end gap-3 pt-2">
                                <Link href={route('accounts.index')}>
                                    <Button type="button" variant="outline">Cancel</Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating…' : 'Create Account'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}