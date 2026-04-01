import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Reply, Send } from 'lucide-react';
import { useEffect } from 'react';

// ─── Types ────────────────────────────────────────────────────────────────────

interface AccountOption {
    id: number;
    name: string;
    account_number: string;
    email: string;
}

interface ReplyTo {
    id: number;
    from_email: string;
    from_name?: string | null;
    subject: string;
    message_id?: string | null;
}

interface ComposeProps {
    accounts: AccountOption[];
    preAccount?: AccountOption | null;
    replyTo?: ReplyTo | null;
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function ComposeEmail() {
    const { accounts, preAccount, replyTo } = usePage<SharedData & ComposeProps>().props;

    const isReply = !!replyTo;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Emails', href: route('emails.index') },
        { title: isReply ? 'Reply' : 'Compose', href: '#' },
    ];

    const { data, setData, post, processing, errors, reset } = useForm({
        to:          isReply ? replyTo!.from_email : (preAccount?.email || ''),
        subject:     isReply
                        ? (replyTo!.subject.startsWith('Re: ') ? replyTo!.subject : `Re: ${replyTo!.subject}`)
                        : '',
        body:        '',
        account_id:  preAccount?.id ? String(preAccount.id) : '',
        in_reply_to: replyTo?.message_id || '',
    });

    // When account picker changes, auto-fill the To field
    const handleAccountChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const id = e.target.value;
        setData('account_id', id);
        if (id) {
            const acc = accounts.find((a) => String(a.id) === id);
            if (acc) setData('to', acc.email);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('emails.send'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isReply ? 'Reply' : 'Compose Email'} />
            <div className="flex flex-1 flex-col items-center p-4">
                <div className="w-full max-w-2xl">

                    {/* Header */}
                    <div className="mb-4 flex items-center gap-3">
                        <Link href={route('emails.index')}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft size={14} className="mr-1" /> Back
                            </Button>
                        </Link>
                        <h1 className="text-xl font-bold flex items-center gap-2">
                            {isReply ? <Reply size={20} /> : <Send size={20} />}
                            {isReply ? `Reply to ${replyTo!.from_name || replyTo!.from_email}` : 'Compose Email'}
                        </h1>
                    </div>

                    {/* Reply context */}
                    {isReply && (
                        <div className="mb-4 rounded-md bg-muted/50 border px-4 py-2 text-sm text-muted-foreground">
                            Replying to: <span className="font-medium text-foreground">{replyTo!.subject}</span>
                        </div>
                    )}

                    <Card>
                        <CardHeader className="px-6 pt-5 pb-2">
                            <CardTitle className="text-base">
                                {isReply ? 'Your reply' : 'New message'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-6 pb-6">
                            <form onSubmit={handleSubmit} className="space-y-4">

                                {/* Account picker (optional convenience) */}
                                {!isReply && (
                                    <div className="space-y-1.5">
                                        <Label>Account (optional — auto-fills To)</Label>
                                        <select
                                            value={data.account_id}
                                            onChange={handleAccountChange}
                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        >
                                            <option value="">— Select account —</option>
                                            {accounts.map((a) => (
                                                <option key={a.id} value={String(a.id)}>
                                                    {a.name} ({a.account_number}) — {a.email}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}

                                {/* To */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="to">To *</Label>
                                    <Input
                                        id="to"
                                        type="email"
                                        value={data.to}
                                        onChange={(e) => setData('to', e.target.value)}
                                        placeholder="recipient@example.com"
                                        disabled={isReply}
                                    />
                                    {errors.to && <p className="text-xs text-destructive">{errors.to}</p>}
                                </div>

                                {/* Subject */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="subject">Subject *</Label>
                                    <Input
                                        id="subject"
                                        value={data.subject}
                                        onChange={(e) => setData('subject', e.target.value)}
                                        placeholder="Email subject"
                                    />
                                    {errors.subject && <p className="text-xs text-destructive">{errors.subject}</p>}
                                </div>

                                {/* Body */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="body">Message *</Label>
                                    <Textarea
                                        id="body"
                                        rows={12}
                                        value={data.body}
                                        onChange={(e) => setData('body', e.target.value)}
                                        placeholder="Write your message here… (HTML is supported)"
                                        className="font-mono text-sm resize-y"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        You can use basic HTML tags (e.g. &lt;b&gt;, &lt;p&gt;, &lt;br&gt;).
                                    </p>
                                    {errors.body && <p className="text-xs text-destructive">{errors.body}</p>}
                                </div>

                                {/* Hidden threading fields */}
                                <input type="hidden" name="in_reply_to" value={data.in_reply_to} />

                                {/* Actions */}
                                <div className="flex items-center justify-end gap-3 pt-2">
                                    <Link href={route('emails.index')}>
                                        <Button type="button" variant="outline">Cancel</Button>
                                    </Link>
                                    <Button type="submit" disabled={processing}>
                                        <Send size={15} className="mr-2" />
                                        {processing ? 'Sending…' : 'Send Email'}
                                    </Button>
                                </div>

                            </form>
                        </CardContent>
                    </Card>

                </div>
            </div>
        </AppLayout>
    );
}