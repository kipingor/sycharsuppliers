import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import Pagination from '@/components/pagination';
import { debounce } from 'lodash';
import { useState } from 'react';
import { Inbox, Mail, MailOpen, Paperclip, PenSquare, Send, CheckCircle2, AlertCircle } from 'lucide-react';

// ─── Types ────────────────────────────────────────────────────────────────────

interface EmailItem {
    id: number;
    direction: 'inbound' | 'outbound';
    from_email: string;
    from_name?: string | null;
    recipient_email: string;
    subject: string;
    status: string;
    attachments?: unknown[] | null;
    read_at?: string | null;
    created_at: string;
    account?: { id: number; name: string; account_number: string } | null;
}

interface EmailsIndexProps {
    emails: { data: EmailItem[]; links: { url: string | null; label: string; active: boolean }[] };
    unreadCount: number;
    accounts: { id: number; name: string; account_number: string }[];
    filters: { tab?: string; search?: string; account_id?: string };
    can: { compose: boolean };
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Emails', href: route('emails.index') }];

const STATUS_CLASS: Record<string, string> = {
    sent:       'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    delivered:  'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    opened:     'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    failed:     'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    bounced:    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    complained: 'bg-orange-100 text-orange-700',
    queued:     'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
    received:   'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    read:       'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
    clicked:    'bg-purple-100 text-purple-700',
};

const STATUS_ICON: Record<string, React.ReactNode> = {
    delivered: <CheckCircle2 size={11} />,
    failed:    <AlertCircle size={11} />,
    bounced:   <AlertCircle size={11} />,
};

// ─── Component ────────────────────────────────────────────────────────────────

export default function EmailsIndex() {
    const { emails, unreadCount, accounts, filters, can } =
        usePage<SharedData & EmailsIndexProps>().props;

    const tab = filters.tab || 'inbox';
    const [search, setSearch] = useState(filters.search || '');

    const push = (params: Record<string, string | undefined>) =>
        router.get(route('emails.index'), { tab, ...filters, ...params }, {
            preserveState: true, replace: true,
        });

    const debouncedSearch = debounce((q: string) => push({ search: q || undefined }), 300);

    const switchTab = (t: string) =>
        router.get(route('emails.index'), { tab: t }, { preserveScroll: false });

    const tabs = [
        { id: 'inbox', label: 'Inbox', icon: Inbox,  badge: unreadCount },
        { id: 'sent',  label: 'Sent',  icon: Send,   badge: 0 },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Emails" />
            <div className="flex flex-1 flex-col gap-4 p-4">

                {/* ── Header ── */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Mail size={22} className="text-blue-600" />
                        <h1 className="text-2xl font-bold">Emails</h1>
                        {unreadCount > 0 && (
                            <Badge className="bg-blue-600 text-white text-xs">
                                {unreadCount} unread
                            </Badge>
                        )}
                    </div>
                    {can.compose && (
                        <Link href={route('emails.compose')}>
                            <Button size="sm">
                                <PenSquare size={15} className="mr-2" /> Compose
                            </Button>
                        </Link>
                    )}
                </div>

                {/* ── Tabs ── */}
                <div className="flex gap-0 border-b border-border">
                    {tabs.map(({ id, label, icon: Icon, badge }) => (
                        <button
                            key={id}
                            onClick={() => switchTab(id)}
                            className={`flex items-center gap-2 px-5 py-2.5 text-sm font-medium border-b-2 transition-colors ${
                                tab === id
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <Icon size={15} />
                            {label}
                            {badge > 0 && (
                                <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-bold text-white">
                                    {badge > 99 ? '99+' : badge}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                {/* ── Filters ── */}
                <div className="flex flex-wrap gap-2">
                    <Input
                        placeholder="Search subject or email…"
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); debouncedSearch(e.target.value); }}
                        className="w-64"
                    />
                    <select
                        value={filters.account_id || ''}
                        onChange={(e) => push({ account_id: e.target.value || undefined })}
                        className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="">All accounts</option>
                        {accounts.map((a) => (
                            <option key={a.id} value={String(a.id)}>
                                {a.name} ({a.account_number})
                            </option>
                        ))}
                    </select>
                </div>

                {/* ── Email rows ── */}
                {emails.data.length === 0 ? (
                    <div className="py-20 text-center">
                        <Mail size={44} className="mx-auto mb-3 text-muted-foreground/20" />
                        <p className="text-muted-foreground text-sm">
                            {tab === 'inbox' ? 'Your inbox is empty.' : 'No sent emails yet.'}
                        </p>
                        {can.compose && tab === 'sent' && (
                            <Link href={route('emails.compose')}>
                                <Button size="sm" className="mt-4">Compose an Email</Button>
                            </Link>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="rounded-lg border overflow-hidden">
                            {emails.data.map((email) => {
                                const unread = email.direction === 'inbound' && !email.read_at;
                                return (
                                    <Link
                                        key={email.id}
                                        href={route('emails.show', email.id)}
                                        className={`flex items-center gap-3 px-4 py-3 hover:bg-muted/50 transition-colors border-b last:border-b-0 ${
                                            unread ? 'bg-blue-50/50 dark:bg-blue-950/10' : ''
                                        }`}
                                    >
                                        {/* Read/unread icon */}
                                        <div className="shrink-0 w-5">
                                            {unread
                                                ? <Mail size={16} className="text-blue-600" />
                                                : <MailOpen size={16} className="text-muted-foreground/50" />}
                                        </div>

                                        {/* Sender / recipient */}
                                        <div className="w-44 shrink-0">
                                            <p className={`text-sm truncate ${unread ? 'font-semibold' : 'text-muted-foreground'}`}>
                                                {email.direction === 'inbound'
                                                    ? (email.from_name || email.from_email)
                                                    : `→ ${email.recipient_email}`}
                                            </p>
                                            {email.account && (
                                                <p className="text-xs text-muted-foreground/70 truncate">
                                                    {email.account.account_number}
                                                </p>
                                            )}
                                        </div>

                                        {/* Subject */}
                                        <p className={`flex-1 min-w-0 text-sm truncate ${unread ? 'font-semibold' : ''}`}>
                                            {email.subject}
                                        </p>

                                        {/* Attachment indicator */}
                                        {email.attachments && email.attachments.length > 0 && (
                                            <Paperclip size={13} className="text-muted-foreground shrink-0" />
                                        )}

                                        {/* Outbound status badge */}
                                        {email.direction === 'outbound' && (
                                            <Badge className={`text-xs shrink-0 gap-1 ${STATUS_CLASS[email.status] || 'bg-gray-100 text-gray-600'}`}>
                                                {STATUS_ICON[email.status]}
                                                {email.status}
                                            </Badge>
                                        )}

                                        {/* Date */}
                                        <span className="text-xs text-muted-foreground shrink-0 w-20 text-right">
                                            {new Date(email.created_at).toLocaleDateString()}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>

                        <Pagination links={emails.links} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}