<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Employee Controller
 * 
 * Handles employee management following thin controller pattern.
 * 
 * @package App\Http\Controllers
 */
class EmployeeController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of employees
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $query = Employee::with('user');

        // Apply filters
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('idnumber', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($position = $request->input('position')) {
            $query->where('position', $position);
        }

        $employees = $query->latest('hire_date')->paginate(15);

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'status', 'position']),
            'positions' => Employee::select('position')->distinct()->pluck('position'),
            'can' => [
                'create' => Auth::user()->can('create', Employee::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new employee
     */
    public function create(): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('employees/create', [
            'users' => User::doesntHave('employee')
                ->select('id', 'name', 'email')
                ->get(),
            'positions' => Employee::select('position')->distinct()->pluck('position'),
        ]);
    }

    /**
     * Store a newly created employee
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create($request->validated());

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Employee created successfully');
    }

    /**
     * Display the specified employee
     */
    public function show(Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $employee->load('user');

        return Inertia::render('employees/show', [
            'employee' => $employee,
            'can' => [
                'update' => Auth::user()->can('update', $employee),
                'delete' => Auth::user()->can('delete', $employee),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified employee
     */
    public function edit(Employee $employee): Response
    {
        $this->authorize('update', $employee);

        return Inertia::render('employees/edit', [
            'employee' => $employee->load('user'),
            'users' => User::doesntHave('employee')
                ->orWhere('id', $employee->user_id)
                ->select('id', 'name', 'email')
                ->get(),
            'positions' => Employee::select('position')->distinct()->pluck('position'),
        ]);
    }

    /**
     * Update the specified employee
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Employee updated successfully');
    }

    /**
     * Remove the specified employee
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Employee deleted successfully');
    }
}
