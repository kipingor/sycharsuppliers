<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use App\Models\Billing;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Meter;
use App\Models\Payment;
use App\Models\Resident;
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
                // 'permissions' => [
                //     'billing' => [
                //         'create' => $request->user()->can('create', Billing::class),
                //     ],
                //     'employee' => [
                //         'create' => $request->user()->can('create', Employee::class),
                //     ],
                //     'expense' => [
                //         'create' => $request->user()->can('create', Expense::class),
                //     ],
                //     'meter' => [
                //         'create' => $request->user()->can('create', Meter::class),
                //     ],
                //     'payment' => [
                //         'create' => $request->user()->can('create', Payment::class),
                //     ],
                //     'resident' => [
                //         'create' => $request->user()->can('create', Resident::class),
                //     ],
                // ]
            ],
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ]
        ];
    }
}
