<?php

namespace App\Providers;

use App\Policies\BillingPolicy;
use App\Policies\EmailLogPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\ExpensePolicy;
use App\Policies\MeterPolicy;
use App\Policies\MeterReadingPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ResidentPolicy;
use App\Policies\SystemSettingPolicy;
use App\Policies\TaxDocumentPolicy;
use App\Policies\TaxPolicy;
use App\Services\SystemSettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(\App\Models\SystemSetting::class, SystemSettingPolicy::class);

        if (Schema::hasTable('system_settings')) {
            app(SystemSettingsService::class)->applyRuntimeConfiguration();
        }
    }
}
