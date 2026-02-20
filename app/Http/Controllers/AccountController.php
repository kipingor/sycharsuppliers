<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Resident;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = Account::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->with('meters:id,account_id,meter_number,status')
            ->withCount(['billings', 'payments'])
            ->latest()
            ->paginate(20);

        return Inertia::render('accounts/index', [
            'accounts' => $accounts,
            'filters' => $request->only(['search', 'status']),
            'can' => [
                'create' => $request->user()->can('create', Account::class),
                'generateFromResidents' => $request->user()->can('generateAccounts', Account::class),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('accounts/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'status' => 'required|in:active,suspended,inactive',
        ]);

        // Generate unique account number
        $lastAccount = Account::orderBy('id', 'desc')->first();
        $nextId = $lastAccount ? $lastAccount->id + 1 : 1;
        $validated['account_number'] = 'ACC' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
        $validated['activated_at'] = $validated['status'] === 'active' ? now() : null;

        $account = Account::create($validated);

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Account created successfully');
    }

    public function show(Account $account)
    {
        $account->load([
            'meters' => function($query) {
                $query->with('readings')->latest();
            },
            'billings' => function($query) {
                $query->latest()->limit(10);
            },
            'payments' => function($query) {
                $query->latest()->limit(10);
            }
        ]);

        $balance = $account->getCurrentBalance();

        return Inertia::render('accounts/show', [
            'account' => $account,
            'balance' => $balance,
            'can' => [
                'update' => Auth::user()->can('update', $account),
                'delete' => Auth::user()->can('delete', $account),
            ],
        ]);
    }

    public function edit(Account $account)
    {
        return Inertia::render('accounts/edit', [
            'account' => $account,
        ]);
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'status' => 'required|in:active,suspended,inactive',
        ]);

        // Handle status changes
        if ($validated['status'] !== $account->status) {
            if ($validated['status'] === 'active') {
                $validated['activated_at'] = now();
                $validated['suspended_at'] = null;
            } elseif ($validated['status'] === 'suspended') {
                $validated['suspended_at'] = now();
            }
        }

        $account->update($validated);

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Account updated successfully');
    }

    public function destroy(Account $account)
    {
        // Soft delete
        $account->delete();

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account deleted successfully');
    }

    /**
     * Generate accounts from existing residents
     */
    public function generateFromResidents(Request $request)
    {
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (&$created, &$skipped) {
            $residents = Resident::whereDoesntHave('account')->get();

            foreach ($residents as $resident) {
                // Check if account with same name already exists
                if (Account::where('name', $resident->name)->exists()) {
                    $skipped++;
                    continue;
                }

                $lastAccount = Account::orderBy('id', 'desc')->first();
                $nextId = $lastAccount ? $lastAccount->id + 1 : 1;

                Account::create([
                    'account_number' => 'ACC' . str_pad($nextId, 6, '0', STR_PAD_LEFT),
                    'name' => $resident->name,
                    'email' => $resident->email,
                    'phone' => $resident->phone,
                    'address' => $resident->address,
                    'status' => 'active',
                    'activated_at' => now(),
                ]);

                // Link resident to account
                $resident->update([
                    'account_id' => Account::where('name', $resident->name)->first()->id
                ]);

                $created++;
            }
        });

        return redirect()
            ->route('accounts.index')
            ->with('success', "Created {$created} accounts. Skipped {$skipped} duplicates.");
    }
}