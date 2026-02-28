<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    // ── Apply a credit note to a specific bill ─────────────────────────────────
    public function store(Request $request, Billing $billing)
    {
        if ($billing->status === 'voided') {
            return back()->withErrors(['billing' => 'Cannot apply a credit note to a voided bill.']);
        }

        // balance is a computed accessor (total_amount - paid_amount - credits),
        // so we read it but never write it back to the DB.
        $currentBalance = $billing->balance;

        $data = $request->validate([
            'type'                => 'required|in:previous_resident_debt,billing_error,goodwill,other',
            'amount'              => ['required', 'numeric', 'min:0.01', "max:{$currentBalance}"],
            'reason'              => 'required|string|min:10',
            'previous_account_id' => 'nullable|exists:accounts,id',
        ]);

        DB::transaction(function () use ($data, $billing, $currentBalance) {
            // 1. Persist the credit note (this is the source of truth for the credit)
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

            // 2. Compute new effective balance after the credit
            $newBalance = max(0, $currentBalance - $data['amount']);

            // 3. Only update status/paid_at — never write computed columns
            $updates = [];

            if ($newBalance <= 0) {
                $updates['status']  = 'paid';
                $updates['paid_at'] = now();
            } elseif ($billing->paid_amount > 0 && $billing->status === 'pending') {
                $updates['status'] = 'partially_paid';
            }

            if (!empty($updates)) {
                $billing->update($updates);
            }
        });

        return redirect()
            ->route('billings.show', $billing->id)
            ->with('success', 'Credit note applied successfully.');
    }

    // ── Void a credit note (reverses the adjustment) ───────────────────────────
    public function void(Request $request, CreditNote $creditNote)
    {
        if ($creditNote->status === 'voided') {
            return back()->withErrors(['credit_note' => 'This credit note is already voided.']);
        }

        $data = $request->validate([
            'void_reason' => 'required|string|min:10',
        ]);

        DB::transaction(function () use ($data, $creditNote) {
            // 1. Void the credit note first
            $creditNote->update([
                'status'      => 'voided',
                'void_reason' => $data['void_reason'],
                'voided_at'   => now(),
                'voided_by'   => Auth::id(),
            ]);

            // 2. Re-read billing so computed accessors (balance, paid_amount)
            //    reflect the now-voided credit note
            $billing     = $creditNote->billing->fresh(['allocations.payment', 'creditNotes']);
            $newBalance  = $billing->balance; // accessor now excludes the voided credit

            // 3. Only update status — never write computed columns
            $updates = [];

            if ($newBalance <= 0) {
                // Still fully settled by payments
                $updates['status']  = 'paid';
                $updates['paid_at'] = $billing->paid_at ?? now();
            } elseif ($billing->status === 'paid') {
                // Was marked paid only because of the credit — revert
                $updates['status']  = $billing->paid_amount > 0 ? 'partially_paid' : 'pending';
                $updates['paid_at'] = null;
            }

            if (!empty($updates)) {
                $billing->update($updates);
            }
        });

        return back()->with('success', 'Credit note voided and billing balance restored.');
    }
}