<?php

namespace App\Providers;

use App\Events\Billing\BillGenerated;
use App\Events\Billing\BillOverdue;
use App\Events\Billing\LateFeeApplied;
use App\Events\Billing\PaymentReceived;
use App\Events\Billing\PaymentReconciled;
use App\Listeners\BillGeneratedListener;
use App\Listeners\BillOverdueListener;
use App\Listeners\LateFeeAppliedListener;
use App\Listeners\PaymentReceivedListener;
use App\Listeners\PaymentReconciledListener;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Payment Events
        PaymentReceived::class => [
            PaymentReceivedListener::class,
        ],
        
        PaymentReconciled::class => [
            PaymentReconciledListener::class,
        ],

        // Billing Events
        BillGenerated::class => [
            BillGeneratedListener::class,
        ],

        BillOverdue::class => [
            BillOverdueListener::class,
        ],

        // Late Fee Events
        LateFeeApplied::class => [
            LateFeeAppliedListener::class,
        ],
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
