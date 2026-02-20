<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleBasedRedirect
{
    /**
     * Handle an incoming request.
     *
     * Redirect users with only 'record-meter-readings' permission to the meter readings create page
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Check if user only has record-meter-readings permission (and no other major permissions)
            $hasOnlyMeterReadingPermission = $user->hasPermissionTo('record-meter-readings') 
                && !$user->hasAnyPermission([
                    'manage-users',
                    'manage-bills',
                    'manage-residents',
                    'process-payments',
                    'view-reports',
                    'manage-meters'
                ]);

            // If accessing dashboard and user only has meter reading permission, redirect to meter readings
            if ($hasOnlyMeterReadingPermission && $request->routeIs('dashboard')) {
                return redirect()->route('meter-readings.create');
            }

            // If accessing routes they don't have permission for, redirect to meter readings
            if ($hasOnlyMeterReadingPermission && !$request->routeIs(['meter-readings.*', 'profile.*', 'password.*', 'logout'])) {
                return redirect()->route('meter-readings.create');
            }
        }

        return $next($request);
    }
}