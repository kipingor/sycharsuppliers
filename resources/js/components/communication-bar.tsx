import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { Bell, Megaphone, MessageCircle, Send, ShieldAlert, Users, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

type Summary = {
    notifications: {
        count: number;
        items: { id: string; title: string; body: string; created_at: string }[];
    };
    priorities: {
        id: number;
        sender: string;
        message: string;
        priority_level: 'critical' | 'high' | 'normal';
        acknowledged_at: string | null;
        created_at: string;
    }[];
    announcements: { id: number; title: string; body: string; priority_level: string; published_at: string }[];
    operations: { failed_payments: number; overdue_bills: number; pending_readings: number };
    recipients: { users: { id: number; name: string }[]; roles: string[] };
};

type Conversation = {
    id: number;
    user: { id: number; name: string } | null;
    last_message: string | null;
    unread_count: number;
};

type ChatMessage = { id: number; sender_id: number; sender: string; body: string; created_at: string };
type ChatUser = { id: number; name: string };

const EMPTY_SUMMARY: Summary = {
    notifications: { count: 0, items: [] },
    priorities: [],
    announcements: [],
    operations: { failed_payments: 0, overdue_bills: 0, pending_readings: 0 },
    recipients: { users: [], roles: [] },
};

export default function CommunicationBar() {
    const { auth } = usePage<SharedData>().props;
    const [summary, setSummary] = useState<Summary | null>(null);
    const pollFailed = useRef(false);
    const [open, setOpen] = useState(false);
    const [activePanel, setActivePanel] = useState<'overview' | 'chat' | 'priority'>('overview');
    const [conversations, setConversations] = useState<Conversation[]>([]);
    const [availableUsers, setAvailableUsers] = useState<ChatUser[]>([]);
    const [conversationId, setConversationId] = useState<number | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [chatText, setChatText] = useState('');
    const [chatRecipient, setChatRecipient] = useState('');
    const [priorityRecipient, setPriorityRecipient] = useState('');
    const [priorityMessage, setPriorityMessage] = useState('');
    const [priorityLevel, setPriorityLevel] = useState<'critical' | 'high' | 'normal'>('high');
    const [recipientType, setRecipientType] = useState<'user' | 'role'>('user');

    const refresh = useCallback(async () => {
        try {
            const response = await axios.get<Summary>(route('communications.summary'));
            setSummary(response.data);
            pollFailed.current = false;
        } catch {
            if (!pollFailed.current) {
                toast.error('Notifications could not be refreshed. Retrying automatically.');
                pollFailed.current = true;
            }
        }
    }, []);

    useEffect(() => {
        void refresh();
        const timer = window.setInterval(() => void refresh(), 30_000);
        return () => window.clearInterval(timer);
    }, [refresh]);

    const loadConversations = useCallback(async () => {
        try {
            const response = await axios.get<{ conversations: Conversation[]; users: ChatUser[] }>(route('communications.conversations.index'));
            setConversations(response.data.conversations);
            setAvailableUsers(response.data.users);
        } catch {
            toast.error('Unable to load conversations.');
        }
    }, []);

    const loadMessages = useCallback(
        async (id: number) => {
            try {
                const response = await axios.get<{ messages: ChatMessage[] }>(route('communications.conversations.messages', id));
                setMessages(response.data.messages);
                setConversationId(id);
                void refresh();
            } catch {
                toast.error('Unable to load chat messages.');
            }
        },
        [refresh],
    );

    useEffect(() => {
        if (!open || activePanel !== 'chat') return;
        void loadConversations();
        const timer = window.setInterval(() => {
            void loadConversations();
            if (conversationId) void loadMessages(conversationId);
        }, 5_000);
        return () => window.clearInterval(timer);
    }, [open, activePanel, conversationId, loadConversations, loadMessages]);

    const acknowledge = async (id: number) => {
        try {
            await axios.post(route('communications.priority.acknowledge', id));
            await refresh();
        } catch {
            toast.error('Unable to acknowledge this priority message.');
        }
    };

    const markNotificationRead = async (id: string) => {
        try {
            await axios.post(route('communications.notifications.read', id));
            await refresh();
        } catch {
            toast.error('Unable to mark the notification as read.');
        }
    };

    const sendPriority = async (event: FormEvent) => {
        event.preventDefault();
        if (!priorityRecipient || !priorityMessage.trim()) return;

        try {
            await axios.post(route('communications.priority.store'), {
                [recipientType === 'user' ? 'recipient_id' : 'recipient_role']:
                    recipientType === 'user' ? Number(priorityRecipient) : priorityRecipient,
                message: priorityMessage,
                priority_level: priorityLevel,
            });
            toast.success('Priority message sent.');
            setPriorityMessage('');
            await refresh();
        } catch {
            toast.error('Unable to send the priority message.');
        }
    };

    const startChat = async (event: FormEvent) => {
        event.preventDefault();
        if (!chatRecipient) return;

        try {
            const response = await axios.post<{ id: number }>(route('communications.conversations.store'), { user_id: Number(chatRecipient) });
            setConversationId(response.data.id);
            setChatRecipient('');
            await loadConversations();
            await loadMessages(response.data.id);
        } catch {
            toast.error('Unable to start a conversation.');
        }
    };

    const sendChat = async (event: FormEvent) => {
        event.preventDefault();
        if (!conversationId || !chatText.trim()) return;

        try {
            await axios.post(route('communications.conversations.messages.store', conversationId), { body: chatText });
            setChatText('');
            await loadMessages(conversationId);
            await loadConversations();
        } catch {
            toast.error('Unable to send the chat message.');
        }
    };

    const unreadPriorityCount = summary?.priorities.length ?? 0;
    const activeCritical =
        summary?.priorities.find((message) => message.priority_level === 'critical') ??
        summary?.priorities.find((message) => message.priority_level === 'high');

    const currentSummary = summary ?? EMPTY_SUMMARY;

    return (
        <>
            {activeCritical && (
                <div
                    className={`fixed inset-x-3 bottom-16 z-[70] mx-auto max-w-xl rounded-lg border-2 p-4 shadow-2xl ${activeCritical.priority_level === 'critical' ? 'border-red-600 bg-red-50 text-red-950 dark:bg-red-950 dark:text-red-50' : 'border-amber-500 bg-amber-50 text-amber-950 dark:bg-amber-950 dark:text-amber-50'}`}
                    role="alertdialog"
                    aria-live="assertive"
                    aria-label="Priority message requiring acknowledgment"
                >
                    <div className="flex items-start gap-3">
                        <ShieldAlert
                            className={`mt-1 size-6 shrink-0 animate-pulse ${activeCritical.priority_level === 'critical' ? 'text-red-600' : 'text-amber-600'}`}
                        />
                        <div className="min-w-0 flex-1">
                            <p className="font-bold">
                                {activeCritical.priority_level === 'critical' ? 'Critical' : 'High priority'} message from {activeCritical.sender} —
                                acknowledgment required
                            </p>
                            <p className="mt-1 text-sm whitespace-pre-wrap">{activeCritical.message}</p>
                            <Button className="mt-3" variant="destructive" onClick={() => void acknowledge(activeCritical.id)}>
                                Acknowledge
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            <div className="bg-background/95 fixed right-3 bottom-3 z-[60] flex items-center gap-1 rounded-full border p-1 shadow-lg backdrop-blur sm:right-4 sm:bottom-4">
                <Button
                    className="relative size-9 rounded-full p-0"
                    size="icon"
                    variant="ghost"
                    title="Notifications and operations"
                    aria-label="Open notifications and operations"
                    onClick={() => {
                        setOpen(true);
                        setActivePanel('overview');
                    }}
                >
                    <Bell className="size-4" />
                    {currentSummary.notifications.count > 0 && (
                        <span className="bg-primary text-primary-foreground absolute -top-0.5 -right-0.5 min-w-4 rounded-full px-1 text-[10px] leading-4">
                            {currentSummary.notifications.count > 99 ? '99+' : currentSummary.notifications.count}
                        </span>
                    )}
                </Button>
                <Button
                    className="relative size-9 rounded-full p-0"
                    size="icon"
                    variant="ghost"
                    title="Priority messages"
                    aria-label="Open priority messages"
                    onClick={() => {
                        setOpen(true);
                        setActivePanel('overview');
                    }}
                >
                    <ShieldAlert className={`size-4 ${unreadPriorityCount ? 'animate-pulse text-red-600' : ''}`} />
                    {unreadPriorityCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 min-w-4 rounded-full bg-red-600 px-1 text-[10px] leading-4 text-white">
                            {unreadPriorityCount > 99 ? '99+' : unreadPriorityCount}
                        </span>
                    )}
                </Button>
                <Button
                    className="relative size-9 rounded-full p-0"
                    size="icon"
                    variant="ghost"
                    title="System updates"
                    aria-label="Open system updates"
                    onClick={() => {
                        setOpen(true);
                        setActivePanel('overview');
                    }}
                >
                    <Megaphone className="size-4" />
                    {currentSummary.announcements.some((item) => item.priority_level === 'critical' || item.priority_level === 'high') && (
                        <span className="absolute top-1 right-1 size-2 rounded-full bg-amber-500" />
                    )}
                </Button>
                <Button
                    className="relative size-9 rounded-full p-0"
                    size="icon"
                    variant="ghost"
                    title="Team chat"
                    aria-label="Open team chat"
                    onClick={() => {
                        setOpen(true);
                        setActivePanel('chat');
                    }}
                >
                    <MessageCircle className="size-4" />
                </Button>
            </div>

            {open && activePanel === 'chat' && (
                <section
                    className="bg-background fixed right-3 bottom-[4.5rem] z-[80] flex h-[min(620px,calc(100dvh-6rem))] w-[min(860px,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-2xl border shadow-2xl sm:right-4 sm:bottom-20"
                    role="dialog"
                    aria-modal="false"
                    aria-label="Internal team chat"
                >
                    <header className="flex items-center justify-between bg-gradient-to-r from-blue-700 to-indigo-700 px-4 py-3 text-white">
                        <div className="flex min-w-0 items-center gap-3">
                            <span className="grid size-10 shrink-0 place-items-center rounded-full bg-white/15">
                                <MessageCircle className="size-5" />
                            </span>
                            <div className="min-w-0">
                                <h2 className="truncate font-semibold">Team chat</h2>
                                <p className="text-xs text-blue-100">
                                    {conversationId
                                        ? (conversations.find((conversation) => conversation.id === conversationId)?.user?.name ??
                                          'Internal conversation')
                                        : 'Internal messaging · refreshes every 5 seconds'}
                                </p>
                            </div>
                        </div>
                        <Button
                            className="size-9 rounded-full text-white hover:bg-white/15 hover:text-white"
                            size="icon"
                            variant="ghost"
                            aria-label="Close chat"
                            onClick={() => setOpen(false)}
                        >
                            <X className="size-4" />
                        </Button>
                    </header>

                    <div className="grid min-h-0 flex-1 grid-cols-1 sm:grid-cols-[minmax(12rem,0.72fr)_1.28fr]">
                        <aside className="bg-muted/20 flex max-h-32 flex-col border-b sm:max-h-none sm:border-r sm:border-b-0">
                            <form className="flex gap-2 border-b p-3" onSubmit={(event) => void startChat(event)}>
                                <select
                                    required
                                    aria-label="Start conversation with"
                                    className="bg-background h-9 min-w-0 flex-1 rounded-full border px-3 text-xs"
                                    value={chatRecipient}
                                    onChange={(event) => setChatRecipient(event.target.value)}
                                >
                                    <option value="">Start a chat...</option>
                                    {availableUsers.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name}
                                        </option>
                                    ))}
                                </select>
                                <Button type="submit" className="size-9 rounded-full" size="icon" aria-label="Start conversation">
                                    <Users className="size-4" />
                                </Button>
                            </form>
                            <div className="min-h-0 flex-1 space-y-1 overflow-y-auto p-2">
                                {conversations.map((conversation) => (
                                    <button
                                        key={conversation.id}
                                        type="button"
                                        className={`hover:bg-muted flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors ${conversation.id === conversationId ? 'bg-blue-100/80 dark:bg-blue-950/50' : ''}`}
                                        onClick={() => void loadMessages(conversation.id)}
                                    >
                                        <span className="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-xs font-semibold text-white">
                                            {(conversation.user?.name ?? '?').slice(0, 1).toUpperCase()}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center justify-between gap-2 text-sm font-medium">
                                                <span className="truncate">{conversation.user?.name ?? 'Conversation'}</span>
                                                {conversation.unread_count > 0 && (
                                                    <span className="rounded-full bg-blue-600 px-1.5 text-[10px] text-white">
                                                        {conversation.unread_count}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="text-muted-foreground mt-0.5 block truncate text-xs">
                                                {conversation.last_message || 'No messages yet'}
                                            </span>
                                        </span>
                                    </button>
                                ))}
                                {conversations.length === 0 && (
                                    <p className="text-muted-foreground px-3 py-2 text-xs">Start a conversation with a teammate.</p>
                                )}
                            </div>
                        </aside>

                        <section className="flex min-h-0 flex-col">
                            {conversationId ? (
                                <>
                                    <div className="from-background to-muted/30 flex-1 space-y-3 overflow-y-auto bg-gradient-to-b px-4 py-5">
                                        {messages.map((message) => {
                                            const outgoing = message.sender_id === auth.user.id;

                                            return (
                                                <div key={message.id} className={`flex ${outgoing ? 'justify-end' : 'justify-start'}`}>
                                                    <article
                                                        className={`max-w-[84%] rounded-2xl px-3.5 py-2.5 shadow-sm ${
                                                            outgoing
                                                                ? 'rounded-br-md bg-blue-600 text-white'
                                                                : 'bg-background text-foreground rounded-bl-md border'
                                                        }`}
                                                    >
                                                        {!outgoing && (
                                                            <p className="mb-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-300">
                                                                {message.sender}
                                                            </p>
                                                        )}
                                                        <p className="text-sm break-words whitespace-pre-wrap">{message.body}</p>
                                                        <time
                                                            className={`mt-1 block text-right text-[10px] ${outgoing ? 'text-blue-100' : 'text-muted-foreground'}`}
                                                            dateTime={message.created_at}
                                                        >
                                                            {new Date(message.created_at).toLocaleTimeString([], {
                                                                hour: 'numeric',
                                                                minute: '2-digit',
                                                            })}
                                                        </time>
                                                    </article>
                                                </div>
                                            );
                                        })}
                                        {messages.length === 0 && (
                                            <p className="bg-muted text-muted-foreground m-auto rounded-full px-4 py-2 text-center text-xs">
                                                Say hello to start the conversation.
                                            </p>
                                        )}
                                    </div>
                                    <form className="bg-background flex items-center gap-2 border-t p-3" onSubmit={(event) => void sendChat(event)}>
                                        <Input
                                            aria-label="Chat message"
                                            className="h-10 rounded-full px-4"
                                            value={chatText}
                                            onChange={(event) => setChatText(event.target.value)}
                                            placeholder="Write a message..."
                                            maxLength={4000}
                                        />
                                        <Button
                                            type="submit"
                                            className="size-10 shrink-0 rounded-full bg-blue-600 hover:bg-blue-700"
                                            size="icon"
                                            disabled={!chatText.trim()}
                                            aria-label="Send message"
                                        >
                                            <Send className="size-4" />
                                        </Button>
                                    </form>
                                </>
                            ) : (
                                <div className="m-auto px-6 text-center">
                                    <span className="mx-auto mb-3 grid size-14 place-items-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200">
                                        <MessageCircle className="size-6" />
                                    </span>
                                    <h3 className="font-medium">Your team conversations</h3>
                                    <p className="text-muted-foreground mt-1 text-sm">Select a teammate or start a new chat.</p>
                                </div>
                            )}
                        </section>
                    </div>
                </section>
            )}

            <Sheet open={open && activePanel !== 'chat'} onOpenChange={setOpen}>
                <SheetContent side="bottom" className="z-[65] max-h-[85vh] overflow-y-auto rounded-t-xl px-4 pb-8">
                    <SheetHeader className="px-0">
                        <SheetTitle className="flex items-center gap-2">
                            <Bell className="size-5" />
                            {activePanel === 'priority' ? 'Priority messages' : 'Notifications & operations'}
                        </SheetTitle>
                        <SheetDescription>Updates refresh automatically. Critical messages stay visible until acknowledged.</SheetDescription>
                    </SheetHeader>

                    <div className="mb-4 flex flex-wrap gap-2 border-b pb-3">
                        <Button size="sm" variant={activePanel === 'overview' ? 'default' : 'outline'} onClick={() => setActivePanel('overview')}>
                            Overview
                        </Button>
                        <Button size="sm" variant={activePanel === 'priority' ? 'default' : 'outline'} onClick={() => setActivePanel('priority')}>
                            Send priority message
                        </Button>
                    </div>

                    {activePanel === 'overview' && (
                        <div className="grid gap-6 md:grid-cols-2">
                            <section className="space-y-3">
                                <h3 className="font-semibold">Unread notifications ({currentSummary.notifications.count})</h3>
                                {currentSummary.notifications.items.length === 0 && (
                                    <p className="text-muted-foreground text-sm">No unread notifications.</p>
                                )}
                                {currentSummary.notifications.items.map((item) => (
                                    <article key={item.id} className="rounded-lg border p-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium">{item.title}</p>
                                                <p className="text-muted-foreground mt-1 text-sm whitespace-pre-wrap">{item.body}</p>
                                            </div>
                                            <Button size="sm" variant="outline" onClick={() => void markNotificationRead(item.id)}>
                                                Mark read
                                            </Button>
                                        </div>
                                    </article>
                                ))}
                            </section>
                            <section className="space-y-3">
                                <h3 className="font-semibold">Priority messages</h3>
                                {currentSummary.priorities.length === 0 && (
                                    <p className="text-muted-foreground text-sm">No unacknowledged messages.</p>
                                )}
                                {currentSummary.priorities.map((message) => (
                                    <article
                                        key={message.id}
                                        className={`rounded-lg border p-3 ${message.priority_level === 'critical' ? 'border-red-500 bg-red-50 dark:bg-red-950/30' : message.priority_level === 'high' ? 'border-amber-500' : ''}`}
                                    >
                                        <p className="text-xs font-semibold uppercase">
                                            {message.priority_level} · {message.sender}
                                        </p>
                                        <p className="mt-1 text-sm whitespace-pre-wrap">{message.message}</p>
                                        <Button
                                            className="mt-2"
                                            size="sm"
                                            variant={message.priority_level === 'critical' ? 'destructive' : 'outline'}
                                            onClick={() => void acknowledge(message.id)}
                                        >
                                            Acknowledge
                                        </Button>
                                    </article>
                                ))}
                            </section>
                            <section className="space-y-3 md:col-span-2">
                                <h3 className="font-semibold">System updates</h3>
                                {currentSummary.announcements.length === 0 && (
                                    <p className="text-muted-foreground text-sm">No current announcements.</p>
                                )}
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {currentSummary.announcements.map((announcement) => (
                                        <article
                                            key={announcement.id}
                                            className={`rounded-lg border p-3 ${announcement.priority_level === 'critical' ? 'border-red-500' : ''}`}
                                        >
                                            <p className="font-medium">
                                                {announcement.title}{' '}
                                                <span className="text-muted-foreground text-xs uppercase">· {announcement.priority_level}</span>
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-sm whitespace-pre-wrap">{announcement.body}</p>
                                        </article>
                                    ))}
                                </div>
                            </section>
                            <section className="bg-muted/50 rounded-lg p-4 md:col-span-2">
                                <h3 className="mb-2 font-semibold">Operations at a glance</h3>
                                <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                    <span>
                                        Failed payments: <strong>{currentSummary.operations.failed_payments}</strong>
                                    </span>
                                    <span>
                                        Overdue bills: <strong>{currentSummary.operations.overdue_bills}</strong>
                                    </span>
                                    <span>
                                        Pending meter readings: <strong>{currentSummary.operations.pending_readings}</strong>
                                    </span>
                                </div>
                            </section>
                        </div>
                    )}

                    {activePanel === 'priority' && (
                        <div className="grid gap-6 md:grid-cols-2">
                            <form className="space-y-4 rounded-lg border p-4" onSubmit={(event) => void sendPriority(event)}>
                                <h3 className="font-semibold">Send priority message</h3>
                                <div className="flex gap-2">
                                    <select
                                        aria-label="Recipient type"
                                        className="bg-background h-10 rounded-md border px-2 text-sm"
                                        value={recipientType}
                                        onChange={(event) => {
                                            setRecipientType(event.target.value as 'user' | 'role');
                                            setPriorityRecipient('');
                                        }}
                                    >
                                        <option value="user">User</option>
                                        <option value="role">Role</option>
                                    </select>
                                    <select
                                        required
                                        aria-label="Recipient"
                                        className="bg-background h-10 min-w-0 flex-1 rounded-md border px-3 text-sm"
                                        value={priorityRecipient}
                                        onChange={(event) => setPriorityRecipient(event.target.value)}
                                    >
                                        <option value="">Select recipient</option>
                                        {recipientType === 'user'
                                            ? currentSummary.recipients.users.map((user) => (
                                                  <option key={user.id} value={user.id}>
                                                      {user.name}
                                                  </option>
                                              ))
                                            : currentSummary.recipients.roles.map((role) => (
                                                  <option key={role} value={role}>
                                                      {role}
                                                  </option>
                                              ))}
                                    </select>
                                </div>
                                <select
                                    aria-label="Priority level"
                                    className="bg-background h-10 w-full rounded-md border px-3 text-sm"
                                    value={priorityLevel}
                                    onChange={(event) => setPriorityLevel(event.target.value as typeof priorityLevel)}
                                >
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical (acknowledgment required)</option>
                                </select>
                                <textarea
                                    required
                                    maxLength={2000}
                                    aria-label="Priority message"
                                    className="bg-background min-h-28 w-full rounded-md border p-3 text-sm"
                                    placeholder="Write an internal message..."
                                    value={priorityMessage}
                                    onChange={(event) => setPriorityMessage(event.target.value)}
                                />
                                <Button type="submit">
                                    <Send className="mr-2 size-4" />
                                    Send message
                                </Button>
                            </form>
                            <section className="space-y-3">
                                <h3 className="font-semibold">Awaiting your acknowledgment</h3>
                                {currentSummary.priorities.map((message) => (
                                    <article
                                        key={message.id}
                                        className={`rounded-lg border p-3 ${message.priority_level === 'critical' ? 'border-red-500 bg-red-50 dark:bg-red-950/30' : ''}`}
                                    >
                                        <p className="text-xs font-semibold uppercase">
                                            {message.priority_level} · {message.sender}
                                        </p>
                                        <p className="my-2 text-sm whitespace-pre-wrap">{message.message}</p>
                                        <Button
                                            size="sm"
                                            variant={message.priority_level === 'critical' ? 'destructive' : 'outline'}
                                            onClick={() => void acknowledge(message.id)}
                                        >
                                            Acknowledge
                                        </Button>
                                    </article>
                                ))}
                            </section>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}
