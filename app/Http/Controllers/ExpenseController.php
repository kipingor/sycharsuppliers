<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseBudget;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::with(['approver:id,name', 'rejector:id,name']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('receipt_number', 'like', "%{$search}%");
            });
        }

        if ($statusFilter = $request->input('status')) {
            match ($statusFilter) {
                'approved' => $query->approved(),
                'rejected' => $query->rejected(),
                'pending'  => $query->pending(),
                default    => null,
            };
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($from = $request->input('from_date')) {
            $query->where('expense_date', '>=', $from);
        }

        if ($to = $request->input('to_date')) {
            $query->where('expense_date', '<=', $to);
        }

        $expenses = $query->latest('expense_date')->paginate(15)->withQueryString();

        $baseQuery = Expense::query();
        if ($category) {
            $baseQuery->where('category', $category);
        }
        if ($from) {
            $baseQuery->where('expense_date', '>=', $from);
        }
        if ($to) {
            $baseQuery->where('expense_date', '<=', $to);
        }

        $summary = [
            'total'    => (float) (clone $baseQuery)->sum('amount'),
            'approved' => (float) (clone $baseQuery)->approved()->sum('amount'),
            'pending'  => (clone $baseQuery)->pending()->count(),
        ];

        return Inertia::render('expenses/index', [
            'expenses'   => $expenses,
            'summary'    => $summary,
            'filters'    => $request->only(['search', 'status', 'category', 'from_date', 'to_date']),
            'categories' => Expense::select('category')->distinct()->orderBy('category')->pluck('category'),
            'can'        => ['create' => Auth::user()->can('create', Expense::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Expense::class);

        $year  = now()->year;
        $month = now()->month;

        $budgets = ExpenseBudget::where('active', true)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->map(fn ($b) => [
                'category'      => $b->category,
                'monthly_limit' => (float) $b->monthly_limit,
                'spent'         => $b->spentAmount(),
                'remaining'     => $b->remainingAmount(),
                'percent_used'  => $b->percentUsed(),
            ]);

        return Inertia::render('expenses/create', [
            'categories' => Expense::select('category')->distinct()->orderBy('category')->pluck('category'),
            'budgets'    => $budgets,
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('receipt_file')) {
            $data['receipt_path'] = $request->file('receipt_file')->store('receipts', 'public');
        }

        unset($data['receipt_file']);
        $expense = Expense::create($data);

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense recorded successfully.');
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);
        $expense->load(['approver:id,name', 'rejector:id,name']);

        $budget     = $expense->getBudget();
        $budgetInfo = $budget ? [
            'monthly_limit' => (float) $budget->monthly_limit,
            'spent'         => $budget->spentAmount(),
            'remaining'     => $budget->remainingAmount(),
            'percent_used'  => $budget->percentUsed(),
            'is_over'       => $budget->isOverBudget(),
        ] : null;

        return Inertia::render('expenses/show', [
            'expense' => $expense,
            'budget'  => $budgetInfo,
            'can'     => [
                'update'  => Auth::user()->can('update', $expense),
                'delete'  => Auth::user()->can('delete', $expense),
                'approve' => Auth::user()->can('approve', $expense),
                'reject'  => Auth::user()->can('reject', $expense),
            ],
        ]);
    }

    public function edit(Expense $expense): Response
    {
        $this->authorize('update', $expense);

        return Inertia::render('expenses/edit', [
            'expense'    => $expense,
            'categories' => Expense::select('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('receipt_file')) {
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $data['receipt_path'] = $request->file('receipt_file')->store('receipts', 'public');
        }

        unset($data['receipt_file']);
        $expense->update($data);

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Expense deleted.');
    }

    public function approve(Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);
        $expense->approve(Auth::id());
        return back()->with('success', 'Expense approved.');
    }

    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('reject', $expense);
        $request->validate(['reason' => 'nullable|string|max:500']);
        $expense->reject(Auth::id(), $request->input('reason'));
        return back()->with('success', 'Expense rejected.');
    }

    public function budgets(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $budgets = ExpenseBudget::where('year', $year)
            ->where('month', $month)
            ->where('active', true)
            ->orderBy('category')
            ->get()
            ->map(fn ($b) => [
                'id'            => $b->id,
                'category'      => $b->category,
                'monthly_limit' => (float) $b->monthly_limit,
                'spent'         => $b->spentAmount(),
                'remaining'     => $b->remainingAmount(),
                'percent_used'  => $b->percentUsed(),
                'is_over'       => $b->isOverBudget(),
                'notes'         => $b->notes,
            ]);

        return Inertia::render('expenses/budgets', [
            'budgets'    => $budgets,
            'categories' => Expense::select('category')->distinct()->orderBy('category')->pluck('category'),
            'year'       => $year,
            'month'      => $month,
            'can'        => ['manage' => Auth::user()->hasRole('admin')],
        ]);
    }

    public function storeBudget(Request $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $data = $request->validate([
            'category'      => 'required|string|max:255',
            'monthly_limit' => 'required|numeric|min:1|max:9999999',
            'year'          => 'required|integer|min:2020|max:2100',
            'month'         => 'required|integer|min:1|max:12',
            'notes'         => 'nullable|string|max:500',
        ]);

        $data['created_by'] = Auth::id();
        $data['active']     = true;

        ExpenseBudget::updateOrCreate(
            ['category' => $data['category'], 'year' => $data['year'], 'month' => $data['month']],
            $data
        );

        return back()->with('success', 'Budget saved.');
    }

    public function destroyBudget(ExpenseBudget $budget): RedirectResponse
    {
        $this->authorize('create', Expense::class);
        $budget->delete();
        return back()->with('success', 'Budget removed.');
    }
}
