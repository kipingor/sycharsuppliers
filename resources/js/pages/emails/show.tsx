import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ArrowLeft, CheckCircle2, AlertCircle, Eye, ExternalLink,
    Mail, Paperclip, Reply, Send, Trash,
} from 'lucide-react';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Attachment { name: string; mime: string; size: number; path: string; }

interface ThreadMessage {
    id: number;
    direction: 'inbound' | 'outbound';
    from_email: string;
    from_name?: string | null;
    recipient_email: string;
    subject: string;
    body: string;
    status: string;
    attachments?: Attachment[] | null;
    message_id?: string | null;
    created_at: string;
    read_at?: string | null;
}

interface EmailDetail {
    id: number;
    direction: 'inbound' | 'outbound';
    from_email: string;
    from_name?: string | null;
    recipient_email: string;
    subject: string;
    body: string;
    status: string;
    message_id?: string | null;
    in_reply_to?: string | null;
    attachments?: Attachment[] | null;
    created_at: string;
    sent_at?: string | null;
    read_at?: string | null;
    delivered_at?: string | null;
    opened_at?: string | null;
    bounced_at?: string | null;
    account?: { id: number; name: string; account_number: string; email?: string; phone?: string } | null;
}

interface ShowEmailProps {
    email: EmailDetail;
    thread: ThreadMessage[];
    can: { reply: boolean; delete: boolean };
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const STATUS_CLASS: Record<string, string> = {
    sent:       'bg-blue-100 text-blue-700',
    delivered:  'bg-green-100 text-green-700',
    opened:     'bg-emerald-100 text-emerald-700',
    failed:     'bg-red-100 text-red-700',
    bounced:    'bg-red-100 text-red-700',
    complained: 'bg-orange-100 text-orange-700',
    queued:     'bg-yellow-100 text-yellow-700',
    received:   'bg-sky-100 text-sky-700',
    read:       'bg-gray-100 text-gray-500',
    clicked:    'bg-purple-100 text-purple-700',
};

function fmtBytes(b: number): string {
    if (b < 1024) return `${b} B`;
    if (b < 1048576) return `${(b / 1024).toFixed(1)} KB`;
    return `${(b / 1048576).toFixed(1)} MB`;
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function ShowEmail() {
    const { email, thread, can } = usePage<SharedData & ShowEmailProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Emails', href: route('emails.index') },
        { title: email.subject, href: '#' },
    ];

    const handleDelete = () => {
        if (confirm('Delete this email from logs?'))
            router.delete(route('emails.destroy', email.id));
    };

    // Delivery timeline shown for outbound emails
    const timeline = email.direction === 'outbound' ? [
        { label: 'Sent',      time: email.sent_at,      icon: Send,         done: !!email.sent_at,      warn: false },
        { label: 'Delivered', time: email.delivered_at, icon: CheckCircle2, done: !!email.delivered_at, warn: false },
        { label: 'Opened',    time: email.opened_at,    icon: Eye,          done: !!email.opened_at,    warn: false },
        { label: 'Bounced',   time: email.bounced_at,   icon: AlertCircle,  done: !!email.bounced_at,   warn: true  },
    ] : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={email.subject} />
            <div className="flex flex-1 flex-col gap-4 p-4 max-w-5xl mx-auto w-full">

                {/* ── Header ── */}
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3 min-w-0">
                        <Link href={route('emails.index', { tab: email.direction === 'inbound' ? 'inbox' : 'sent' })}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft size={14} className="mr-1" /> Back
                            </Button>
                        </Link>
                        <h1 className="text-lg font-semibold truncate">{email.subject}</h1>
                    </div>
                    <div className="flex gap-2 shrink-0">
                        {can.reply && email.direction === 'inbound' && (
                            <Link href={route('emails.reply', email.id)}>
                                <Button size="sm">
                                    <Reply size={14} className="mr-1" /> Reply
                                </Button>
                            </Link>
                        )}
                        {can.delete && (
                            <Button variant="destructive" size="sm" onClick={handleDelete}>
                                <Trash size={14} className="mr-1" /> Delete
                            </Button>
                        )}
                    </div>
                </div>

                {/* ── Main grid ── */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

                    {/* Thread / body column */}
                    <div className="lg:col-span-2 space-y-3">
                        {thread.map((msg) => (
                            <Card
                                key={msg.id}
                                className={msg.id === email.id ? 'ring-2 ring-blue-500/40' : 'opacity-75'}
                            >
                                <CardHeader className="px-4 pt-4 pb-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            {msg.direction === 'inbound' ? (
                                                <p className="text-sm font-medium truncate">
                                                    {msg.from_name
                                                        ? <>{msg.from_name} <span className="font-normal text-muted-foreground">&lt;{msg.from_email}&gt;</span></>
                                                        : msg.from_email}
                                                </p>
                                            ) : (
                                                <p className="text-sm text-muted-foreground truncate">
                                                    You &rarr; <span className="font-medium text-foreground">{msg.recipient_email}</span>
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground mt-0.5">
                                                {new Date(msg.created_at).toLocaleString()}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-1.5 shrink-0">
                                            {msg.direction === 'outbound' && (
                                                <Badge className={`text-xs ${STATUS_CLASS[msg.status] || ''}`}>
                                                    {msg.status}
                                                </Badge>
                                            )}
                                            {msg.direction === 'inbound' && !msg.read_at && (
                                                <Badge className="text-xs bg-blue-600 text-white">Unread</Badge>
                                            )}
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="px-4 pb-4">
                                    {/* Email body */}
                                    <div
                                        className="prose prose-sm dark:prose-invert max-w-none text-sm leading-relaxed border rounded-md p-3 bg-muted/20 overflow-auto"
                                        dangerouslySetInnerHTML={{ __html: msg.body }}
                                    />

                                    {/* Attachments */}
                                    {msg.attachments && msg.attachments.length > 0 && (
                                        <div className="mt-3 space-y-1.5">
                                            <p className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                                <Paperclip size={12} />
                                                {msg.attachments.length} attachment{msg.attachments.length !== 1 ? 's' : ''}
                                            </p>
                                            {msg.attachments.map((att, i) => (
                                                <a
                                                    key={i}
                                                    href={`/storage/${att.path}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="flex items-center gap-2 text-xs bg-muted rounded px-2.5 py-1.5 text-blue-600 hover:underline"
                                                >
                                                    <Paperclip size={11} />
                                                    <span className="flex-1 truncate">{att.name}</span>
                                                    <span className="text-muted-foreground shrink-0">{fmtBytes(att.size)}</span>
                                                    <ExternalLink size={10} className="shrink-0" />
                                                </a>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">

                        {/* Account card */}
                        {email.account && (
                            <Card>
                                <CardHeader className="px-4 pt-4 pb-2">
                                    <CardTitle className="text-sm">Account</CardTitle>
                                </CardHeader>
                                <CardContent className="px-4 pb-4 space-y-1.5">
                                    <Link
                                        href={route('accounts.show', email.account.id)}
                                        className="flex items-center gap-1 text-sm font-medium text-blue-600 hover:underline"
                                    >
                                        {email.account.name} <ExternalLink size={11} />
                                    </Link>
                                    <p className="text-xs text-muted-foreground">{email.account.account_number}</p>
                                    {email.account.email && <p className="text-xs">{email.account.email}</p>}
                                    {email.account.phone && <p className="text-xs">{email.account.phone}</p>}
                                </CardContent>
                            </Card>
                        )}

                        {/* Delivery timeline — outbound only */}
                        {email.direction === 'outbound' && (
                            <Card>
                                <CardHeader className="px-4 pt-4 pb-2">
                                    <CardTitle className="text-sm">Delivery Tracking</CardTitle>
                                </CardHeader>
                                <CardContent className="px-4 pb-4 space-y-2">
                                    {timeline.map(({ label, time, icon: Icon, done, warn }) => (
                                        <div key={label} className={`flex items-center gap-2 text-sm ${done ? '' : 'opacity-35'}`}>
                                            <Icon size={14} className={done ? (warn ? 'text-red-500' : 'text-green-600') : 'text-muted-foreground'} />
                                            <span className={done ? 'font-medium' : ''}>{label}</span>
                                            {time && (
                                                <span className="ml-auto text-xs text-muted-foreground">
                                                    {new Date(time).toLocaleDateString()}
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {/* Metadata */}
                        <Card>
                            <CardHeader className="px-4 pt-4 pb-2">
                                <CardTitle className="text-sm">Details</CardTitle>
                            </CardHeader>
                            <CardContent className="px-4 pb-4 space-y-2.5">
                                {[
                                    ['Direction',  email.direction],
                                    ['From',       email.from_email],
                                    ['To',         email.recipient_email],
                                    ['Date',       new Date(email.created_at).toLocaleString()],
                                    ...(email.message_id ? [['Message-Id', email.message_id]] : []),
                                ].map(([label, value]) => (
                                    <div key={label}>
                                        <p className="text-[11px] text-muted-foreground uppercase tracking-wide">{label}</p>
                                        <p className="text-xs break-all">{value}</p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                    </div>
                </div>
            </div>
        </AppLayout>
    );
}