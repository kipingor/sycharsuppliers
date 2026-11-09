<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\EmailLog;
use App\Services\Billing\AccountStatementGenerator;
use App\Services\EmailService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private EmailService $emailService,
        private AccountStatementGenerator $statementGenerator,
    ) {}

    // ─── Inbox / Sent list ─────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EmailLog::class);

        $tab       = $request->input('tab', 'inbox'); // inbox | sent
        $search    = $request->input('search', '');
        $accountId = $request->input('account_id');

        $query = $tab === 'inbox'
            ? EmailLog::inbound()->with('account:id,name,account_number')
            : EmailLog::outbound()->with('account:id,name,account_number');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('from_email', 'like', "%{$search}%")
                    ->orWhere('recipient_email', 'like', "%{$search}%");
            });
        }

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $emails      = $query->latest()->paginate(25)->withQueryString();
        $unreadCount = EmailLog::unread()->count();

        $accounts = Account::whereHas('emailLogs')
            ->select('id', 'name', 'account_number')
            ->orderBy('name')
            ->get();

        return Inertia::render('emails/index', [
            'emails'      => $emails,
            'unreadCount' => $unreadCount,
            'accounts'    => $accounts,
            'filters'     => $request->only(['tab', 'search', 'account_id']),
            'can'         => [
                'compose' => Auth::user()->can('create', EmailLog::class),
            ],
        ]);
    }

    // ─── Thread view ───────────────────────────────────────────────────────

    public function show(EmailLog $emailLog): Response
    {
        $this->authorize('view', $emailLog);

        if ($emailLog->isInbound()) {
            $emailLog->markAsRead();
        }

        $emailLog->load('account:id,name,account_number,email,phone');

        $thread = $emailLog->threadEmails()->map(fn($e) => [
            'id'              => $e->id,
            'direction'       => $e->direction,
            'from_email'      => $e->from_email,
            'from_name'       => $e->from_name,
            'recipient_email' => $e->recipient_email,
            'subject'         => $e->subject,
            'body'            => $e->body,
            'status'          => $e->status,
            'attachments'     => $e->attachments,
            'message_id'      => $e->message_id,
            'created_at'      => $e->created_at,
            'read_at'         => $e->read_at,
        ]);

        return Inertia::render('emails/show', [
            'email'  => $emailLog,
            'thread' => $thread,
            'can'    => [
                'reply'  => Auth::user()->can('create', EmailLog::class),
                'delete' => Auth::user()->can('delete', $emailLog),
            ],
        ]);
    }

    // ─── Compose ──────────────────────────────────────────────────────────

    public function compose(Request $request): Response
    {
        $this->authorize('create', EmailLog::class);

        $initialMode = $request->input('mode') === 'overdue_reminder_individual'
            ? 'overdue_reminder_individual'
            : 'single';

        $preAccount = null;
        if ($acId = $request->input('account_id')) {
            $preAccount = Account::select('id', 'name', 'account_number', 'email')->find($acId);
        }

        $accounts = Account::whereNotNull('email')
            ->where('email', '!=', '')
            ->select('id', 'name', 'account_number', 'email')
            ->orderBy('name')
            ->get();

        $overdueAccountsCount = Account::whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('billings', fn ($query) => $query
                ->whereIn('status', ['pending', 'overdue', 'partially_paid'])
                ->where('due_date', '<', today())
                ->where('amount_due', '>', 0))
            ->count();

        $activeAccountsCount = Account::active()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();

        return Inertia::render('emails/compose', [
            'accounts'               => $accounts,
            'preAccount'             => $preAccount,
            'replyTo'                => null,
            'initialMode'            => $initialMode,
            'overdueAccountsCount'   => $overdueAccountsCount,
            'activeAccountsCount'    => $activeAccountsCount,
        ]);
    }

    // ─── Reply ────────────────────────────────────────────────────────────

    public function reply(EmailLog $emailLog): Response
    {
        $this->authorize('create', EmailLog::class);

        $accounts = Account::whereNotNull('email')
            ->where('email', '!=', '')
            ->select('id', 'name', 'account_number', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('emails/compose', [
            'accounts'   => $accounts,
            'preAccount' => $emailLog->account,
            'replyTo'    => [
                'id'         => $emailLog->id,
                'from_email' => $emailLog->from_email,
                'from_name'  => $emailLog->from_name,
                'subject'    => $emailLog->subject,
                'message_id' => $emailLog->message_id,
            ],
            'initialMode' => 'single',
            'overdueAccountsCount' => 0,
            'activeAccountsCount'  => 0,
        ]);
    }

    // ─── Send ─────────────────────────────────────────────────────────────

    public function send(Request $request): RedirectResponse
    {
        $this->authorize('create', EmailLog::class);

        $data = $request->validate([
            'to'          => 'required|email',
            'subject'     => 'required|string|max:255',
            'body'        => 'required|string',
            'account_id'  => 'nullable|exists:accounts,id',
            'in_reply_to' => 'nullable|string|max:255',
        ]);

        $log = $this->emailService->send(
            $data['to'],
            $data['subject'],
            $data['body'],
            array_filter([
                'account_id'  => $data['account_id']  ?? null,
                'in_reply_to' => $data['in_reply_to'] ?? null,
            ])
        );

        if ($log->status === EmailLog::STATUS_FAILED) {
            return redirect()->route('emails.show', $log)
                ->with('error', 'Email failed to send: ' . $log->error_message);
        }

        return redirect()->route('emails.show', $log)
            ->with('success', 'Email sent successfully.');
    }

    public function sendOverdue(Request $request): RedirectResponse
    {
        $this->authorize('create', EmailLog::class);

        $data = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'subject'    => 'required|string|max:255',
            'body'       => 'required|string|max:20000',
        ]);

        $account = Account::findOrFail($data['account_id']);
        $log = $this->sendOverdueReminder($account, $data['subject'], $data['body']);

        if (!$log) {
            return back()->with('error', 'This account has no overdue balance or saved email address.');
        }

        if ($log->status === EmailLog::STATUS_FAILED) {
            return redirect()->route('emails.show', $log)
                ->with('error', 'Email failed to send: ' . $log->error_message);
        }

        return redirect()->route('emails.show', $log)
            ->with('success', "Overdue reminder sent to {$account->email} with the account statement attached.");
    }

    public function sendBulk(Request $request): RedirectResponse
    {
        $this->authorize('create', EmailLog::class);

        $data = $request->validate([
            'campaign' => 'required|in:overdue_reminder,announcement',
            'subject'  => 'required|string|max:255',
            'body'     => 'required|string|max:20000',
        ]);

        $sent = 0;
        $failed = 0;
        $eligible = 0;

        if ($data['campaign'] === 'overdue_reminder') {
            Account::whereNotNull('email')
                ->where('email', '!=', '')
                ->whereHas('billings', fn ($query) => $query
                    ->whereIn('status', ['pending', 'overdue', 'partially_paid'])
                    ->where('due_date', '<', today())
                    ->where('amount_due', '>', 0))
                ->orderBy('id')
                ->chunkById(100, function ($accounts) use ($data, &$sent, &$failed, &$eligible) {
                    foreach ($accounts as $account) {
                        $log = $this->sendOverdueReminder($account, $data['subject'], $data['body']);
                        if (!$log) {
                            continue;
                        }
                        $eligible++;
                        $log->status === EmailLog::STATUS_FAILED ? $failed++ : $sent++;
                    }
                });
        } else {
            Account::active()
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($accounts) use ($data, &$sent, &$failed, &$eligible) {
                    foreach ($accounts as $account) {
                        $eligible++;

                        $log = $this->emailService->send(
                            $account->email,
                            $data['subject'],
                            $data['body'],
                            ['account_id' => $account->id, 'name' => $account->name]
                        );

                        $log->status === EmailLog::STATUS_FAILED ? $failed++ : $sent++;
                    }
                });
        }

        $redirect = redirect()->route('emails.index');

        if ($failed > 0) {
            return $redirect->with('error', "Bulk email completed with failures: {$sent} sent, {$failed} failed. See the email log for details.");
        }

        if ($eligible === 0) {
            return $redirect->with('warning', 'No eligible accounts were found for this bulk email.');
        }

        return $redirect->with('success', "Bulk email complete: {$sent} sent, {$failed} failed.");
    }

    private function sendOverdueReminder(Account $account, string $subject, string $body): ?EmailLog
    {
        if (!$account->email) {
            return null;
        }

        $overdueAmount = $account->billings()
            ->whereIn('status', ['pending', 'overdue', 'partially_paid'])
            ->where('due_date', '<', today())
            ->where('amount_due', '>', 0)
            ->sum('amount_due');

        if ($overdueAmount <= 0) {
            return null;
        }

        $personalizedBody = strtr($body, [
            '{{name}}' => e($account->name),
            '{{account_number}}' => e($account->account_number),
            '{{overdue_amount}}' => number_format((float) $overdueAmount, 2) . ' KES',
        ]);
        $periodStart = now()->startOfYear()->toDateString();
        $periodEnd = today()->toDateString();
        $statementPdf = $this->statementGenerator
            ->generatePdf($account, $periodStart, $periodEnd)
            ->output();

        return $this->emailService->send(
            $account->email,
            $subject,
            $personalizedBody,
            [
                'account_id' => $account->id,
                'name' => $account->name,
                'attachments' => [[
                    'name' => "account_statement_{$account->account_number}.pdf",
                    'mime' => 'application/pdf',
                    'content' => $statementPdf,
                ]],
            ]
        );
    }

    // ─── Mark read ────────────────────────────────────────────────────────

    public function markRead(EmailLog $emailLog): RedirectResponse
    {
        $this->authorize('update', $emailLog);
        $emailLog->markAsRead();
        return back()->with('success', 'Marked as read.');
    }

    // ─── Delete ───────────────────────────────────────────────────────────

    public function destroy(EmailLog $emailLog): RedirectResponse
    {
        $this->authorize('delete', $emailLog);
        $emailLog->delete();
        return redirect()->route('emails.index')
            ->with('success', 'Email deleted from logs.');
    }
}
