<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\Billing\AccountStatementGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AccountStatementController extends Controller
{
    public function __construct(
        private AccountStatementGenerator $generator
    ) {}

    // ── Show the statement page (preview + controls) ───────────────────────
    public function show(Request $request, Account $account)
    {
        $request->validate([
            'start' => 'nullable|date',
            'end'   => 'nullable|date|after_or_equal:start',
        ]);

        $end   = $request->input('end',   now()->toDateString());
        $start = $request->input('start', now()->startOfYear()->toDateString());

        $data = $this->generator->buildData($account, $start, $end);

        return Inertia::render('accounts/accounts-statement', [
            'account'    => $account->only('id', 'name', 'account_number', 'email', 'phone', 'address'),
            'statement'  => $data,
            'start_date' => $start,
            'end_date'   => $end,
        ]);
    }

    // ── Download PDF ───────────────────────────────────────────────────────
    public function download(Request $request, Account $account)
    {
        $request->validate([
            'start' => 'nullable|date',
            'end'   => 'nullable|date|after_or_equal:start',
        ]);

        $end   = $request->input('end',   now()->toDateString());
        $start = $request->input('start', now()->startOfYear()->toDateString());

        return $this->generator
            ->generatePdf($account, $start, $end)
            ->download("account_statement_{$account->account_number}_{$start}_to_{$end}.pdf");
    }

    // ── Email statement ────────────────────────────────────────────────────
    public function send(Request $request, Account $account)
    {
        $request->validate([
            'email' => 'required|email',
            'start' => 'nullable|date',
            'end'   => 'nullable|date|after_or_equal:start',
        ]);

        $end   = $request->input('end',   now()->toDateString());
        $start = $request->input('start', now()->startOfYear()->toDateString());

        $pdfBinary = $this->generator
            ->generatePdf($account, $start, $end)
            ->output();

        $data = $this->generator->buildData($account, $start, $end);

        Mail::to($request->email)
            ->send(new \App\Mail\AccountStatement($pdfBinary, $account, $data));

        return back()->with('success', "Account statement sent to {$request->email}.");
    }
}