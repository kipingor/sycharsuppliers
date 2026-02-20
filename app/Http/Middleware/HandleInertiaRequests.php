<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use App\Models\Billing;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Meter;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\Report;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'can' => $request->user() ? [
                    'billing' => [
                        'viewAny' => $request->user()?->can('viewAny', Billing::class) ?? false,
                        'create' => $request->user()?->can('create', Billing::class) ?? false,
                        'generate' => $request->user()?->can('generate', Billing::class) ?? false,
                    ],
                    'meter' => [
                        'viewAny' => $request->user()?->can('viewAny', Meter::class) ?? false,
                        'create' => $request->user()?->can('create', Meter::class) ?? false,
                    ],
                    'payment' => [
                        'viewAny' => $request->user()?->can('viewAny', Payment::class) ?? false,
                        'create' => $request->user()?->can('create', Payment::class) ?? false,
                    ],
                    'resident' => [
                        'viewAny' => $request->user()?->can('viewAny', Resident::class) ?? false,
                        'create' => $request->user()?->can('create', Resident::class) ?? false,
                    ],
                    'expense' => [
                        'viewAny' => $request->user()?->can('viewAny', Expense::class) ?? false,
                        'create' => $request->user()?->can('create', Expense::class) ?? false,
                    ],
                    'employee' => [
                        'viewAny' => $request->user()?->can('viewAny', Employee::class) ?? false,
                        'create' => $request->user()?->can('create', Employee::class) ?? false,
                    ],
                    'report' => [
                        'viewAny' => $request->user()->can('viewAny', Report::class),
                    ],
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'ziggy' => fn(): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ]
        ];
    }
}
