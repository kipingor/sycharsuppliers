<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('settings/users', [
            'users' => User::with('roles', 'permissions')->get(),
            'roles' => ['admin', 'accountant', 'field_officer'], // Fetch from DB if dynamic
            'permissions' => ['manage-users', 'manage-customers', 'view-reports', 'manage-bills', 'process-payments', 'record-meter-readings'], // Fetch from DB if dynamic
        ]);
    }
}
