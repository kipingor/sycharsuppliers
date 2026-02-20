<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use App\Policies\BillingPolicy;
use App\Policies\EmailLogPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ResidentPolicy;
use App\Policies\MeterPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\MeterReadingPolicy;
use App\Policies\TaxDocumentPolicy;
use App\Policies\TaxPolicy;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::automaticallyEagerLoadRelationships();
        Gate::policy(\OwenIt\Auditing\Models\Audit::class, \App\Policies\AuditPolicy::class);
        Gate::policy(\App\Models\Billing::class, BillingPolicy::class);
        Gate::policy(\App\Models\EmailLog::class, EmailLogPolicy::class);
        Gate::policy(\App\Models\Employee::class, EmployeePolicy::class);
        Gate::policy(\App\Models\Payment::class, PaymentPolicy::class); 
        Gate::policy(\App\Models\Resident::class, ResidentPolicy::class);
        Gate::policy(\App\Models\Meter::class, MeterPolicy::class);
        Gate::policy(\App\Models\Expense::class, ExpensePolicy::class);
        Gate::policy(\App\Models\MeterReading::class, MeterReadingPolicy::class);
        Gate::policy(\App\Models\TaxDocument::class, TaxDocumentPolicy::class);
        Gate::policy(\App\Models\Tax::class, TaxPolicy::class);
    }
}
