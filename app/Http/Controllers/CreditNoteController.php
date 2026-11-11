<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Billing;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CreditNoteController extends Controller
{
    // ── List all credit notes ──────────────────────────────────────────────────
    public function index(Request $request)
    {
        $notes = CreditNote::with(['billing.account', 'previousAccount', 'createdBy'])
            ->when($request->search, function ($q, $s) {
                $q->where('reference', 'like', "%{$s}%")
                  ->orWhere('reason', 'like', "%{$s}%")
                  ->orWhereHas('billing.account', fn ($q2) => $q2->where('name', 'like', "%{$s}%"));
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('credit-notes/index', [
            'notes'   => $notes,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function applyForm(Request $request)
    {
        $filters = $request->validate([
            'through_year' => 'nullable|integer|min:2000|max:' . now()->year,
            'account_id' => 'nullable|exists:accounts,id',
        ]);
        $throughYear = (int) ($filters['through_year'] ?? now()->year - 2);

        $bills = Billing::query()
            ->with([
                'account:id,name,account_number,status',
                'allocations.payment:id,status,deleted_at',
                'directPayments:id,status,deleted_at',
                'creditNotes:id,billing_id,amount,status',
            ])
            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->where('amount_due', '>', 0)
            ->where('billing_period', '<=', sprintf('%04d-12', $throughYear))
            ->when($filters['account_id'] ?? null, fn ($query, $accountId) => $query->where('account_id', $accountId))
            ->orderBy('billing_period')
            ->orderBy('account_id')
            ->paginate(100)
            ->withQueryString()
            ->through(fn (Billing $bill) => [
                'id' => $bill->id,
                'billing_period' => $bill->billing_period,
                'total_amount' => (float) $bill->total_amount,
                'stored_amount_due' => (float) $bill->amount_due,
                'balance' => $bill->balance,
                'status' => $bill->status,
                'account' => [
                    'id' => $bill->account->id,
                    'name' => $bill->account->name,
                    'account_number' => $bill->account->account_number,
                    'status' => $bill->account->status,
                ],
            ]);

        return Inertia::render('credit-notes/apply', [
            'bills' => $bills,
            'accounts' => Account::orderBy('name', 'asc')->get(['id', 'name', 'account_number', 'status']),
            'filters' => [
                'through_year' => $throughYear,
                'account_id' => $filters['account_id'] ?? '',
            ],
        ]);
    }

    public function applyBulk(Request $request)
    {
        $data = $request->validate([
            'billing_ids' => 'required|array|min:1|max:100',
            'billing_ids.*' => 'required|integer|distinct|exists:billings,id',
            'reason' => 'required|string|min:10|max:2000',
        ]);

        $created = DB::transaction(function () use ($data) {
            $bills = Billing::query()
                ->whereIn('id', $data['billing_ids'], 'and', false)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($bills->count() !== count($data['billing_ids'])) {
                throw ValidationException::withMessages([
                    'billing_ids' => 'One or more selected bills could not be found.',
                ]);
            }

            foreach ($bills as $bill) {
                $bill = Billing::with(['allocations.payment', 'directPayments', 'creditNotes'])
                    ->lockForUpdate()
                    ->findOrFail($bill->id);

                if (!in_array($bill->status, ['pending', 'partially_paid', 'overdue'], true)
                    || (float) $bill->amount_due <= 0
                    || $bill->billing_period > sprintf('%04d-12', now()->year - 1)) {
                    throw ValidationException::withMessages([
                        'billing_ids' => "Bill #{$bill->id} is no longer eligible for a bad-debt write-off.",
                    ]);
                }

                $balance = $bill->balance;
                if ($balance <= 0 || abs($balance - (float) $bill->amount_due) > 0.01) {
                    throw ValidationException::withMessages([
                        'billing_ids' => "Bill #{$bill->id} has a balance conflict. Resolve it before writing it off.",
                    ]);
                }

                CreditNote::create([
                    'billing_id' => $bill->id,
                    'reference' => CreditNote::generateReference(),
                    'type' => 'bad_debt_writeoff',
                    'amount' => $balance,
                    'reason' => $data['reason'],
                    'status' => 'applied',
                    'created_by' => Auth::id(),
                ]);

                $bill->update([
                    'amount_due' => 0,
                    'status' => 'written_off',
                    'paid_at' => null,
                ]);
            }

            return count($bills);
        });

        return redirect()->route('credit-notes.apply')
            ->with('success', "{$created} bill(s) written off. Each bill has its own credit note and audit record.");
    }

    // ── Apply a credit note to a specific bill ─────────────────────────────────
    public function store(Request $request, Billing $billing)
    {
        $data = $request->validate([
            'type'                => 'required|in:previous_resident_debt,billing_error,goodwill,other',
            'amount'              => 'required|numeric|min:0.01',
            'reason'              => 'required|string|min:10',
            'previous_account_id' => 'nullable|exists:accounts,id',
        ]);

        DB::transaction(function () use ($data, $billing) {
            $billing = Billing::with(['allocations.payment', 'directPayments', 'creditNotes'])
                ->lockForUpdate()
                ->findOrFail($billing->id);
            $currentBalance = $billing->balance;

            if (!in_array($billing->status, ['pending', 'partially_paid', 'overdue'], true) || $currentBalance <= 0) {
                throw ValidationException::withMessages([
                    'billing' => 'Only bills with an open balance can receive a credit note.',
                ]);
            }
            if ((float) $data['amount'] > $currentBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'The credit amount exceeds the bill’s current open balance.',
                ]);
            }

            CreditNote::create([
                'billing_id'          => $billing->id,
                'previous_account_id' => $data['previous_account_id'] ?? null,
                'reference'           => CreditNote::generateReference(),
                'type'                => $data['type'],
                'amount'              => $data['amount'],
                'reason'              => $data['reason'],
                'status'              => 'applied',
                'created_by'          => Auth::id(),
            ]);

            $billing->refresh()->load(['allocations.payment', 'directPayments', 'creditNotes']);
            $this->syncBillSettlement($billing);
        });

        return redirect()
            ->route('billings.show', $billing->id)
            ->with('success', 'Credit note applied successfully.');
    }

    // ── Void a credit note (reverses the adjustment) ───────────────────────────
    public function void(Request $request, CreditNote $creditNote)
    {
        $data = $request->validate([
            'void_reason' => 'required|string|min:10',
        ]);

        DB::transaction(function () use ($data, $creditNote) {
            $creditNote = CreditNote::query()->lockForUpdate()->findOrFail($creditNote->id);
            if ($creditNote->status === 'voided') {
                throw ValidationException::withMessages([
                    'credit_note' => 'This credit note is already voided.',
                ]);
            }

            $creditNote->update([
                'status'      => 'voided',
                'void_reason' => $data['void_reason'],
                'voided_at'   => now(),
                'voided_by'   => Auth::id(),
            ]);

            $billing = Billing::with(['allocations.payment', 'directPayments', 'creditNotes'])
                ->lockForUpdate()
                ->findOrFail($creditNote->billing_id);
            $this->syncBillSettlement($billing);
        });

        return back()->with('success', 'Credit note voided and billing balance restored.');
    }

    private function syncBillSettlement(Billing $billing): void
    {
        $balance = $billing->balance;
        $paid = $billing->paid_amount;
        $credited = $billing->creditNotes->where('status', 'applied')->sum('amount');

        if ($balance <= 0) {
            $status = $credited > 0 && $paid < (float) $billing->total_amount
                ? 'written_off'
                : 'paid';
            $paidAt = $status === 'paid' ? ($billing->paid_at ?? now()) : null;
        } else {
            $status = $paid + $credited > 0
                ? 'partially_paid'
                : ($billing->due_date && $billing->due_date->isPast() ? 'overdue' : 'pending');
            $paidAt = null;
        }

        $billing->update([
            'amount_due' => $balance,
            'status' => $status,
            'paid_at' => $paidAt,
        ]);
    }
}