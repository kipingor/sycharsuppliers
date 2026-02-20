<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Expense Controller
 * 
 * Handles expense management following thin controller pattern.
 * All business logic delegated to services where applicable.
 * 
 * @package App\Http\Controllers
 */
class ExpenseController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of expenses
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::with('approver');

        // Apply filters
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('receipt_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'approved') {
                $query->where('status', true);
            } elseif ($status === 'pending') {
                $query->where('status', false);
            }
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($fromDate = $request->input('from_date')) {
            $query->where('expense_date', '>=', $fromDate);
        }

        if ($toDate = $request->input('to_date')) {
            $query->where('expense_date', '<=', $toDate);
        }

        $expenses = $query->latest('expense_date')->paginate(15);

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'filters' => $request->only(['search', 'status', 'category', 'from_date', 'to_date']),
            'categories' => Expense::select('category')->distinct()->pluck('category'),
            'can' => [
                'create' => Auth::user()->can('create', Expense::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new expense
     */
    public function create(): Response
    {
        $this->authorize('create', Expense::class);

        return Inertia::render('expenses/create', [
            'categories' => Expense::select('category')->distinct()->pluck('category'),
        ]);
    }

    /**
     * Store a newly created expense
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = Expense::create($request->validated());

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense created successfully');
    }

    /**
     * Display the specified expense
     */
    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        $expense->load('approver');

        return Inertia::render('expenses/show', [
            'expense' => $expense,
            'can' => [
                'update' => Auth::user()->can('update', $expense),
                'delete' => Auth::user()->can('delete', $expense),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified expense
     */
    public function edit(Expense $expense): Response
    {
        $this->authorize('update', $expense);

        return Inertia::render('expenses/edit', [
            'expense' => $expense,
            'categories' => Expense::select('category')->distinct()->pluck('category'),
        ]);
    }

    /**
     * Update the specified expense
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->update($request->validated());

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense updated successfully');
    }

    /**
     * Remove the specified expense
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Expense deleted successfully');
    }
}
