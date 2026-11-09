import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Reply, Send } from 'lucide-react';
import { useState } from 'react';

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
    overdueAccountsCount: number;
    activeAccountsCount: number;
}

const overdueTemplate = {
    subject: 'Action required: overdue water account balance',
    body: `<p>Dear {{name}},</p>
<p>Our records show an overdue balance of <strong>{{overdue_amount}}</strong> on account {{account_number}}. Please clear the arrears promptly or contact us as soon as possible to discuss how and when you will settle them.</p>
<p>If the arrears are not cleared and we do not hear from you in a timely manner, we may be forced to disconnect your water supply in accordance with our procedures.</p>
<p>If you have noticed an increase in your water usage or believe your bill is incorrect, please inform us early. We also encourage you to check for increased internal usage, leaks, or other plumbing issues.</p>
<p>Your account statement is attached for your reference.</p>
<p>Thank you for your prompt attention.</p>`,
};

const announcementTemplates = {
    pump_repair: {
        subject: 'Notice: temporary water supply interruption',
        body: `<p>Dear Customer,</p>
<p>Please be advised that water supply will be suspended for approximately one to two days while we repair the pump. We apologize for the inconvenience and will restore supply as soon as the repairs are complete.</p>
<p>Thank you for your understanding.</p>`,
    },
    usage_check: {
        subject: 'Notice: please check for leaks and unusual water usage',
        body: `<p>Dear Customer,</p>
<p>Please check your premises for leaks or unusual increases in water usage. Undetected leaks and excess usage can increase your bill charges. If you notice a sudden increase or have concerns about your usage, please contact us promptly.</p>
<p>Thank you for your cooperation.</p>`,
    },
};

// ─── Component ────────────────────────────────────────────────────────────────

export default function ComposeEmail() {
    const { accounts, preAccount, replyTo, overdueAccountsCount, activeAccountsCount } = usePage<SharedData & ComposeProps>().props;

    const isReply = !!replyTo;
    const [mode, setMode] = useState<'single' | 'overdue_reminder' | 'announcement'>('single');
    const [announcementTemplate, setAnnouncementTemplate] = useState<keyof typeof announcementTemplates>('pump_repair');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Emails', href: route('emails.index') },
        { title: isReply ? 'Reply' : 'Compose', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        to: isReply ? replyTo!.from_email : preAccount?.email || '',
        subject: isReply ? (replyTo!.subject.startsWith('Re: ') ? replyTo!.subject : `Re: ${replyTo!.subject}`) : '',
        body: '',
        account_id: preAccount?.id ? String(preAccount.id) : '',
        in_reply_to: replyTo?.message_id || '',
        campaign: '',
    });

    const handleModeChange = (nextMode: 'single' | 'overdue_reminder' | 'announcement') => {
        setMode(nextMode);
        if (nextMode === 'overdue_reminder') {
            setData((current) => ({
                ...current,
                subject: overdueTemplate.subject,
                body: overdueTemplate.body,
                campaign: 'overdue_reminder',
            }));
        } else if (nextMode === 'announcement') {
            const template = announcementTemplates[announcementTemplate];
            setData((current) => ({
                ...current,
                subject: template.subject,
                body: template.body,
                campaign: 'announcement',
            }));
        } else {
            setData('campaign', '');
        }
    };

    const handleAnnouncementTemplateChange = (key: keyof typeof announcementTemplates) => {
        setAnnouncementTemplate(key);
        const template = announcementTemplates[key];
        setData((current) => ({
            ...current,
            subject: template.subject,
            body: template.body,
        }));
    };

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
        if (mode === 'single') {
            post(route('emails.send'));
            return;
        }

        const recipientCount = mode === 'overdue_reminder' ? overdueAccountsCount : activeAccountsCount;
        const audience = mode === 'overdue_reminder' ? 'accounts with overdue balances' : 'active accounts';

        if (!window.confirm(`Send this email to ${recipientCount} ${audience}?`)) {
            return;
        }

        post(route('emails.send-bulk'));
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
                        <h1 className="flex items-center gap-2 text-xl font-bold">
                            {isReply ? <Reply size={20} /> : <Send size={20} />}
                            {isReply ? `Reply to ${replyTo!.from_name || replyTo!.from_email}` : 'Compose Email'}
                        </h1>
                    </div>

                    {/* Reply context */}
                    {isReply && (
                        <div className="bg-muted/50 text-muted-foreground mb-4 rounded-md border px-4 py-2 text-sm">
                            Replying to: <span className="text-foreground font-medium">{replyTo!.subject}</span>
                        </div>
                    )}

                    <Card>
                        <CardHeader className="px-6 pt-5 pb-2">
                            <CardTitle className="text-base">{isReply ? 'Your reply' : 'New message'}</CardTitle>
                        </CardHeader>
                        <CardContent className="px-6 pb-6">
                            <form onSubmit={handleSubmit} className="space-y-4">
                                {!isReply && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="email-mode">Email type</Label>
                                        <select
                                            id="email-mode"
                                            value={mode}
                                            onChange={(e) => handleModeChange(e.target.value as typeof mode)}
                                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                        >
                                            <option value="single">Individual email</option>
                                            <option value="overdue_reminder">Overdue account reminder</option>
                                            <option value="announcement">Announcement to active accounts</option>
                                        </select>
                                    </div>
                                )}

                                {mode === 'announcement' && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="announcement-template">Announcement template</Label>
                                        <select
                                            id="announcement-template"
                                            value={announcementTemplate}
                                            onChange={(e) => handleAnnouncementTemplateChange(e.target.value as keyof typeof announcementTemplates)}
                                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                        >
                                            <option value="pump_repair">Temporary interruption for pump repairs</option>
                                            <option value="usage_check">Check for leaks and unusual usage</option>
                                        </select>
                                    </div>
                                )}

                                {mode !== 'single' && (
                                    <div className="bg-muted/40 text-muted-foreground rounded-md border px-4 py-3 text-sm">
                                        {mode === 'overdue_reminder' ? (
                                            <>
                                                This email will go to {overdueAccountsCount} account
                                                {overdueAccountsCount === 1 ? '' : 's'} with overdue balances and a saved email address, with each
                                                account's current-year statement attached. Use <code>{'{{name}}'}</code>,{' '}
                                                <code>{'{{account_number}}'}</code>, and <code>{'{{overdue_amount}}'}</code> to personalize it.
                                            </>
                                        ) : (
                                            <>
                                                This announcement will go to {activeAccountsCount} active account
                                                {activeAccountsCount === 1 ? '' : 's'} with a saved email address. You can edit the subject and
                                                message below.
                                            </>
                                        )}
                                    </div>
                                )}

                                {/* Account picker (optional convenience) */}
                                {!isReply && mode === 'single' && (
                                    <div className="space-y-1.5">
                                        <Label>Account (optional — auto-fills To)</Label>
                                        <select
                                            value={data.account_id}
                                            onChange={handleAccountChange}
                                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
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
                                {mode === 'single' && (
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
                                        {errors.to && <p className="text-destructive text-xs">{errors.to}</p>}
                                    </div>
                                )}

                                {/* Subject */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="subject">Subject *</Label>
                                    <Input
                                        id="subject"
                                        value={data.subject}
                                        onChange={(e) => setData('subject', e.target.value)}
                                        placeholder="Email subject"
                                    />
                                    {errors.subject && <p className="text-destructive text-xs">{errors.subject}</p>}
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
                                        className="resize-y font-mono text-sm"
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        You can use basic HTML tags (e.g. &lt;b&gt;, &lt;p&gt;, &lt;br&gt;).
                                    </p>
                                    {errors.body && <p className="text-destructive text-xs">{errors.body}</p>}
                                </div>

                                {/* Hidden threading fields */}
                                <input type="hidden" name="in_reply_to" value={data.in_reply_to} />

                                {/* Actions */}
                                <div className="flex items-center justify-end gap-3 pt-2">
                                    <Link href={route('emails.index')}>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </Link>
                                    <Button type="submit" disabled={processing}>
                                        <Send size={15} className="mr-2" />
                                        {processing ? 'Sending…' : mode === 'single' ? 'Send Email' : 'Send to Accounts'}
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
