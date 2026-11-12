import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';

type MailSettings = {
    mailer: 'smtp' | 'resend' | 'mailersend';
    host: string;
    port: string | number;
    username: string;
    password: string;
    api_key: string;
    password_configured?: boolean;
    api_key_configured?: boolean;
    encryption: string;
    from_address: string;
    from_name: string;
};

type BankAccount = {
    bank_name: string;
    account_name: string;
    account_number: string;
    branch: string;
    swift: string;
    currency: string;
};

type MpesaSettings = {
    paybill_number: string;
    account_number: string;
    till_number: string;
    consumer_key: string;
    consumer_secret: string;
    passkey: string;
    security_credential: string;
    consumer_key_configured?: boolean;
    consumer_secret_configured?: boolean;
    passkey_configured?: boolean;
    security_credential_configured?: boolean;
    environment: 'sandbox' | 'production';
    shortcode: string;
    initiator_name: string;
};

type GeneralSettings = {
    company_name: string;
    logo: string;
    address: string;
    phone: string;
    email: string;
    pin_vat_number: string;
    default_unit_rate: string | number;
    billing_day: string | number;
    due_days: string | number;
    late_fee_enabled: boolean;
    late_fee_percentage: string | number;
    email_notifications: boolean;
    sms_notifications: boolean;
    feature_flags: { auto_bill_generation: boolean };
};

type Props = {
    defaults: {
        email: Partial<MailSettings>;
        banking: { bank_accounts: BankAccount[] };
        mpesa: Partial<MpesaSettings>;
        general: Partial<GeneralSettings>;
    };
    settings: {
        email?: Partial<MailSettings>;
        banking?: { bank_accounts?: BankAccount[] };
        mpesa?: Partial<MpesaSettings>;
        general?: Partial<GeneralSettings>;
    };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'System configurations', href: '/settings/system' }];
const sections = ['Email', 'Banking', 'M-Pesa', 'General'] as const;
type Section = (typeof sections)[number];

function Field({
    label,
    value,
    onChange,
    type = 'text',
    placeholder,
}: {
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    type?: string;
    placeholder?: string;
}) {
    return (
        <Label className="space-y-2">
            <span>{label}</span>
            <Input type={type} value={value} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} />
        </Label>
    );
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return (
        <label className="flex items-center gap-3 text-sm">
            <input type="checkbox" className="accent-primary size-4" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            {label}
        </label>
    );
}

function FormSection({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <section className="space-y-5 rounded-lg border p-5">
            <div>
                <h3 className="font-semibold">{title}</h3>
                {description && <p className="text-muted-foreground mt-1 text-sm">{description}</p>}
            </div>
            {children}
        </section>
    );
}

export default function SystemSettings({ settings, defaults }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [activeSection, setActiveSection] = useState<Section>('Email');
    const [busyAction, setBusyAction] = useState<string | null>(null);
    const [mail, setMail] = useState<MailSettings>({
        mailer: 'resend',
        host: '',
        port: 587,
        username: '',
        encryption: 'tls',
        from_address: '',
        from_name: '',
        ...defaults.email,
        ...settings.email,
        password: '',
        api_key: '',
    });
    const [banks, setBanks] = useState<BankAccount[]>(
        settings.banking?.bank_accounts?.length
            ? settings.banking.bank_accounts
            : defaults.banking.bank_accounts.length
              ? defaults.banking.bank_accounts
              : [
                    {
                        bank_name: '',
                        account_name: '',
                        account_number: '',
                        branch: '',
                        swift: '',
                        currency: 'KES',
                    },
                ],
    );
    const [mpesa, setMpesa] = useState<MpesaSettings>({
        paybill_number: '',
        account_number: '',
        till_number: '',
        environment: 'sandbox',
        shortcode: '',
        initiator_name: '',
        ...defaults.mpesa,
        ...settings.mpesa,
        consumer_key: '',
        consumer_secret: '',
        passkey: '',
        security_credential: '',
    });
    const [general, setGeneral] = useState<GeneralSettings>({
        company_name: '',
        logo: '',
        address: '',
        phone: '',
        email: '',
        pin_vat_number: '',
        default_unit_rate: '',
        billing_day: 1,
        due_days: 14,
        late_fee_enabled: false,
        late_fee_percentage: 0,
        email_notifications: true,
        sms_notifications: false,
        ...defaults.general,
        ...settings.general,
        feature_flags: {
            auto_bill_generation: defaults.general.feature_flags?.auto_bill_generation ?? false,
            ...settings.general?.feature_flags,
        },
    });
    const [testRecipient, setTestRecipient] = useState(auth.user.email);
    const [announcement, setAnnouncement] = useState({ title: '', body: '', priority_level: 'normal' });

    const submit = (event: FormEvent, group: string, data: Parameters<typeof router.put>[1]) => {
        event.preventDefault();
        setBusyAction(group);
        router.put(route('settings.system.update', group), data, {
            preserveScroll: true,
            onError: (errors) => toast.error(Object.values(errors)[0] || 'Unable to save settings.'),
            onFinish: () => setBusyAction(null),
        });
    };

    const runTest = (url: string, data: Parameters<typeof router.post>[1], action: string) => {
        setBusyAction(action);
        router.post(url, data, {
            preserveScroll: true,
            onError: (errors) => toast.error(Object.values(errors)[0] || 'Connection test failed.'),
            onFinish: () => setBusyAction(null),
        });
    };

    const publishAnnouncement = (event: FormEvent) => {
        event.preventDefault();
        setBusyAction('announcement');
        router.post(route('communications.announcements.store'), announcement, {
            preserveScroll: true,
            onSuccess: () => {
                setAnnouncement({ title: '', body: '', priority_level: 'normal' });
            },
            onError: (errors) => toast.error(Object.values(errors)[0] || 'Unable to publish announcement.'),
            onFinish: () => setBusyAction(null),
        });
    };

    const updateBank = (index: number, key: keyof BankAccount, value: string) => {
        setBanks((current) => current.map((bank, currentIndex) => (currentIndex === index ? { ...bank, [key]: value } : bank)));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="System configurations" description="Manage the shared email, payment, company and billing settings." />
                    <div className="flex flex-wrap gap-2 border-b pb-3" role="tablist" aria-label="Configuration groups">
                        {sections.map((section) => (
                            <Button
                                key={section}
                                type="button"
                                size="sm"
                                variant={activeSection === section ? 'default' : 'outline'}
                                onClick={() => setActiveSection(section)}
                            >
                                {section}
                            </Button>
                        ))}
                    </div>

                    {activeSection === 'Email' && (
                        <form className="space-y-6" onSubmit={(event) => submit(event, 'email', mail)}>
                            <FormSection
                                title="Email delivery"
                                description="Secrets are encrypted at rest and never sent back to this page. Leave a secret empty to keep its current value."
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="mailer">Provider</Label>
                                        <select
                                            id="mailer"
                                            className="bg-background h-10 w-full rounded-md border px-3 text-sm"
                                            value={mail.mailer}
                                            onChange={(event) => setMail({ ...mail, mailer: event.target.value as MailSettings['mailer'] })}
                                        >
                                            <option value="smtp">SMTP</option>
                                            <option value="resend">Resend</option>
                                            <option value="mailersend">MailerSend</option>
                                        </select>
                                    </div>
                                    <Field
                                        label="From address"
                                        type="email"
                                        value={mail.from_address}
                                        onChange={(value) => setMail({ ...mail, from_address: value })}
                                    />
                                    <Field label="From name" value={mail.from_name} onChange={(value) => setMail({ ...mail, from_name: value })} />
                                    {mail.mailer === 'smtp' ? (
                                        <>
                                            <Field label="SMTP host" value={mail.host} onChange={(value) => setMail({ ...mail, host: value })} />
                                            <Field
                                                label="SMTP port"
                                                type="number"
                                                value={mail.port}
                                                onChange={(value) => setMail({ ...mail, port: value })}
                                            />
                                            <Field
                                                label="SMTP username"
                                                value={mail.username}
                                                onChange={(value) => setMail({ ...mail, username: value })}
                                            />
                                            <Field
                                                label={`SMTP password${mail.password_configured ? ' (configured)' : ''}`}
                                                type="password"
                                                value={mail.password}
                                                placeholder={mail.password_configured ? 'Saved securely; leave empty to keep' : ''}
                                                onChange={(value) => setMail({ ...mail, password: value })}
                                            />
                                            <div className="space-y-2">
                                                <Label htmlFor="encryption">Encryption</Label>
                                                <select
                                                    id="encryption"
                                                    className="bg-background h-10 w-full rounded-md border px-3 text-sm"
                                                    value={mail.encryption}
                                                    onChange={(event) => setMail({ ...mail, encryption: event.target.value })}
                                                >
                                                    <option value="tls">TLS</option>
                                                    <option value="ssl">SSL</option>
                                                    <option value="">None</option>
                                                </select>
                                            </div>
                                        </>
                                    ) : (
                                        <Field
                                            label={`API key${mail.api_key_configured ? ' (configured)' : ''}`}
                                            type="password"
                                            value={mail.api_key}
                                            placeholder={mail.api_key_configured ? 'Saved securely; leave empty to keep' : ''}
                                            onChange={(value) => setMail({ ...mail, api_key: value })}
                                        />
                                    )}
                                </div>
                            </FormSection>
                            <FormSection title="Test delivery">
                                <div className="flex flex-col gap-3 sm:flex-row">
                                    <Input
                                        type="email"
                                        aria-label="Test recipient"
                                        value={testRecipient}
                                        onChange={(event) => setTestRecipient(event.target.value)}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={busyAction !== null}
                                        onClick={() => runTest(route('settings.system.test-email'), { recipient: testRecipient }, 'email-test')}
                                    >
                                        {busyAction === 'email-test' ? 'Sending...' : 'Send test email'}
                                    </Button>
                                </div>
                            </FormSection>
                            <Button type="submit" disabled={busyAction !== null}>
                                {busyAction === 'email' ? 'Saving...' : 'Save email settings'}
                            </Button>
                        </form>
                    )}

                    {activeSection === 'Banking' && (
                        <form className="space-y-6" onSubmit={(event) => submit(event, 'banking', { bank_accounts: banks })}>
                            {banks.map((bank, index) => (
                                <FormSection key={index} title={`Bank account ${index + 1}`}>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Bank name" value={bank.bank_name} onChange={(value) => updateBank(index, 'bank_name', value)} />
                                        <Field
                                            label="Account name"
                                            value={bank.account_name}
                                            onChange={(value) => updateBank(index, 'account_name', value)}
                                        />
                                        <Field
                                            label="Account number"
                                            value={bank.account_number}
                                            onChange={(value) => updateBank(index, 'account_number', value)}
                                        />
                                        <Field label="Branch" value={bank.branch} onChange={(value) => updateBank(index, 'branch', value)} />
                                        <Field label="SWIFT / BIC" value={bank.swift} onChange={(value) => updateBank(index, 'swift', value)} />
                                        <Field
                                            label="Currency"
                                            value={bank.currency}
                                            onChange={(value) => updateBank(index, 'currency', value.toUpperCase())}
                                        />
                                    </div>
                                    {banks.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setBanks(banks.filter((_, bankIndex) => bankIndex !== index))}
                                        >
                                            Remove account
                                        </Button>
                                    )}
                                </FormSection>
                            ))}
                            <div className="flex flex-wrap gap-3">
                                {banks.length < 10 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setBanks([
                                                ...banks,
                                                { bank_name: '', account_name: '', account_number: '', branch: '', swift: '', currency: 'KES' },
                                            ])
                                        }
                                    >
                                        Add bank account
                                    </Button>
                                )}
                                <Button type="submit" disabled={busyAction !== null}>
                                    {busyAction === 'banking' ? 'Saving...' : 'Save banking details'}
                                </Button>
                            </div>
                        </form>
                    )}

                    {activeSection === 'M-Pesa' && (
                        <form className="space-y-6" onSubmit={(event) => submit(event, 'mpesa', mpesa)}>
                            <FormSection
                                title="M-Pesa payments"
                                description="Consumer credentials, passkeys and security credentials are encrypted and never returned to the browser."
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="mpesa-environment">Environment</Label>
                                        <select
                                            id="mpesa-environment"
                                            className="bg-background h-10 w-full rounded-md border px-3 text-sm"
                                            value={mpesa.environment}
                                            onChange={(event) =>
                                                setMpesa({ ...mpesa, environment: event.target.value as MpesaSettings['environment'] })
                                            }
                                        >
                                            <option value="sandbox">Sandbox</option>
                                            <option value="production">Production</option>
                                        </select>
                                    </div>
                                    <Field
                                        label="Paybill number"
                                        value={mpesa.paybill_number}
                                        onChange={(value) => setMpesa({ ...mpesa, paybill_number: value })}
                                    />
                                    <Field
                                        label="Account number"
                                        value={mpesa.account_number}
                                        onChange={(value) => setMpesa({ ...mpesa, account_number: value })}
                                    />
                                    <Field
                                        label="Till number"
                                        value={mpesa.till_number}
                                        onChange={(value) => setMpesa({ ...mpesa, till_number: value })}
                                    />
                                    <Field label="Shortcode" value={mpesa.shortcode} onChange={(value) => setMpesa({ ...mpesa, shortcode: value })} />
                                    <Field
                                        label="Initiator name"
                                        value={mpesa.initiator_name}
                                        onChange={(value) => setMpesa({ ...mpesa, initiator_name: value })}
                                    />
                                    <Field
                                        label={`Consumer key${mpesa.consumer_key_configured ? ' (configured)' : ''}`}
                                        value={mpesa.consumer_key}
                                        placeholder={mpesa.consumer_key_configured ? 'Saved securely; leave empty to keep' : ''}
                                        onChange={(value) => setMpesa({ ...mpesa, consumer_key: value })}
                                    />
                                    <Field
                                        label={`Consumer secret${mpesa.consumer_secret_configured ? ' (configured)' : ''}`}
                                        type="password"
                                        value={mpesa.consumer_secret}
                                        placeholder={mpesa.consumer_secret_configured ? 'Saved securely; leave empty to keep' : ''}
                                        onChange={(value) => setMpesa({ ...mpesa, consumer_secret: value })}
                                    />
                                    <Field
                                        label={`Passkey${mpesa.passkey_configured ? ' (configured)' : ''}`}
                                        type="password"
                                        value={mpesa.passkey}
                                        placeholder={mpesa.passkey_configured ? 'Saved securely; leave empty to keep' : ''}
                                        onChange={(value) => setMpesa({ ...mpesa, passkey: value })}
                                    />
                                    <Field
                                        label={`Security credential${mpesa.security_credential_configured ? ' (configured)' : ''}`}
                                        type="password"
                                        value={mpesa.security_credential}
                                        placeholder={mpesa.security_credential_configured ? 'Saved securely; leave empty to keep' : ''}
                                        onChange={(value) => setMpesa({ ...mpesa, security_credential: value })}
                                    />
                                </div>
                            </FormSection>
                            <div className="flex flex-wrap gap-3">
                                <Button type="submit" disabled={busyAction !== null}>
                                    {busyAction === 'mpesa' ? 'Saving...' : 'Save M-Pesa settings'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busyAction !== null}
                                    onClick={() => runTest(route('settings.system.test-mpesa'), {}, 'mpesa-test')}
                                >
                                    {busyAction === 'mpesa-test' ? 'Testing...' : 'Test M-Pesa connection'}
                                </Button>
                            </div>
                        </form>
                    )}

                    {activeSection === 'General' && (
                        <div className="space-y-8">
                            <form className="space-y-6" onSubmit={(event) => submit(event, 'general', general)}>
                                <FormSection title="Company profile">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            label="Company name"
                                            value={general.company_name}
                                            onChange={(value) => setGeneral({ ...general, company_name: value })}
                                        />
                                        <Field
                                            label="Logo URL or path"
                                            value={general.logo}
                                            onChange={(value) => setGeneral({ ...general, logo: value })}
                                        />
                                        <Field label="Phone" value={general.phone} onChange={(value) => setGeneral({ ...general, phone: value })} />
                                        <Field
                                            label="Company email"
                                            type="email"
                                            value={general.email}
                                            onChange={(value) => setGeneral({ ...general, email: value })}
                                        />
                                        <Field
                                            label="PIN / VAT number"
                                            value={general.pin_vat_number}
                                            onChange={(value) => setGeneral({ ...general, pin_vat_number: value })}
                                        />
                                        <Field
                                            label="Default unit rate"
                                            type="number"
                                            value={general.default_unit_rate}
                                            onChange={(value) => setGeneral({ ...general, default_unit_rate: value })}
                                        />
                                        <div className="sm:col-span-2">
                                            <Field
                                                label="Address"
                                                value={general.address}
                                                onChange={(value) => setGeneral({ ...general, address: value })}
                                            />
                                        </div>
                                    </div>
                                </FormSection>
                                <FormSection title="Billing rules">
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <Field
                                            label="Billing day of month"
                                            type="number"
                                            value={general.billing_day}
                                            onChange={(value) => setGeneral({ ...general, billing_day: value })}
                                        />
                                        <Field
                                            label="Days until due"
                                            type="number"
                                            value={general.due_days}
                                            onChange={(value) => setGeneral({ ...general, due_days: value })}
                                        />
                                        <Field
                                            label="Late fee percentage"
                                            type="number"
                                            value={general.late_fee_percentage}
                                            onChange={(value) => setGeneral({ ...general, late_fee_percentage: value })}
                                        />
                                    </div>
                                    <div className="space-y-3">
                                        <Toggle
                                            label="Enable late fees"
                                            checked={general.late_fee_enabled}
                                            onChange={(value) => setGeneral({ ...general, late_fee_enabled: value })}
                                        />
                                        <Toggle
                                            label="Enable email notifications"
                                            checked={general.email_notifications}
                                            onChange={(value) => setGeneral({ ...general, email_notifications: value })}
                                        />
                                        <Toggle
                                            label="Enable SMS notifications"
                                            checked={general.sms_notifications}
                                            onChange={(value) => setGeneral({ ...general, sms_notifications: value })}
                                        />
                                        <Toggle
                                            label="Automatic bill generation enabled"
                                            checked={general.feature_flags.auto_bill_generation}
                                            onChange={(value) =>
                                                setGeneral({ ...general, feature_flags: { ...general.feature_flags, auto_bill_generation: value } })
                                            }
                                        />
                                    </div>
                                </FormSection>
                                <Button type="submit" disabled={busyAction !== null}>
                                    {busyAction === 'general' ? 'Saving...' : 'Save general settings'}
                                </Button>
                            </form>

                            <form className="space-y-4 rounded-lg border p-5" onSubmit={publishAnnouncement}>
                                <div>
                                    <h3 className="font-semibold">Publish system announcement</h3>
                                    <p className="text-muted-foreground mt-1 text-sm">Visible to all authenticated staff in the notifications bar.</p>
                                </div>
                                <Input
                                    aria-label="Announcement title"
                                    placeholder="Title"
                                    value={announcement.title}
                                    onChange={(event) => setAnnouncement({ ...announcement, title: event.target.value })}
                                />
                                <textarea
                                    aria-label="Announcement details"
                                    className="bg-background min-h-24 w-full rounded-md border p-3 text-sm"
                                    placeholder="Announcement details"
                                    value={announcement.body}
                                    onChange={(event) => setAnnouncement({ ...announcement, body: event.target.value })}
                                />
                                <div className="flex flex-wrap items-center gap-3">
                                    <select
                                        aria-label="Announcement priority"
                                        className="bg-background h-10 rounded-md border px-3 text-sm"
                                        value={announcement.priority_level}
                                        onChange={(event) => setAnnouncement({ ...announcement, priority_level: event.target.value })}
                                    >
                                        <option value="normal">Normal</option>
                                        <option value="high">High</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                    <Button type="submit" disabled={busyAction !== null}>
                                        {busyAction === 'announcement' ? 'Publishing...' : 'Publish announcement'}
                                    </Button>
                                </div>
                            </form>
                            <p className="text-muted-foreground text-xs">
                                Polling-based delivery refreshes the notification bar every 30 seconds. On mobile the bar collapses to a bottom button
                                that opens the full panel.
                            </p>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
