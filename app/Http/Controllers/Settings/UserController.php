<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of users
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);

        return inertia('settings/users', [
            'users' => User::with('roles', 'permissions')
                ->orderBy('name')
                ->get()
                ->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->email_verified_at ? 'Active' : 'Pending',
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'permissions' => $user->permissions->pluck('name')->toArray(),
                    'created_at' => $user->created_at?->format('Y-m-d'),
                ]),
            'roles' => Role::all()->pluck('name')->toArray(),
            'permissions' => Permission::all()->pluck('name')->toArray(),
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Assign roles
        if (!empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        // Assign additional permissions
        if (!empty($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

        return redirect()->route('settings.users.index')
            ->with('success', 'User created successfully');
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        // Update password if provided
        if (!empty($validated['password'])) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        // Sync roles
        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        // Sync permissions
        if (isset($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

        return redirect()->route('settings.users.index')
            ->with('success', 'User updated successfully');
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        // Prevent deleting own account
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account');
        }

        $user->delete();

        return redirect()->route('settings.users.index')
            ->with('success', 'User deleted successfully');
    }
}