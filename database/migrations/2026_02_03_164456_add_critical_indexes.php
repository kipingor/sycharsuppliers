<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add Critical Indexes
 *
 * This migration adds performance-critical indexes across all tables.
 * These indexes optimize common query patterns in the billing system.
 *
 * QUERY PATTERNS OPTIMIZED:
 * - Account balance calculations
 * - Overdue bill detection
 * - Payment reconciliation lookups
 * - Billing period queries
 * - Meter reading ranges
 *
 * PRODUCTION SAFETY:
 * - Uses try/catch to handle existing indexes gracefully
 * - Indexes are additive (safe to run on production)
 * - Uses IF NOT EXISTS pattern where possible
 * - No data modification
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // BILLINGS TABLE INDEXES
        Schema::table('billings', function (Blueprint $table) {
            // Most critical: status + due_date for overdue detection
            $this->addIndexSafely($table, ['status', 'due_date'], 'billings_status_duedate_idx');
            
            // Account billing history queries
            $this->addIndexSafely($table, ['account_id', 'billing_period'], 'billings_account_period_idx');
            
            // Issued date range queries
            $this->addIndexSafely($table, ['issued_at'], 'billings_issued_at_idx');
            
            // Payment tracking
            $this->addIndexSafely($table, ['status', 'paid_at'], 'billings_status_paid_idx');
        });

        // BILLING_DETAILS TABLE INDEXES
        if (Schema::hasTable('billing_details')) {
            Schema::table('billing_details', function (Blueprint $table) {
                // Billing details lookup
                $this->addIndexSafely($table, ['billing_id'], 'billing_details_billing_idx');
            });
        }

        // PAYMENTS TABLE INDEXES
        Schema::table('payments', function (Blueprint $table) {
            // Payment date range queries (reports)
            $this->addIndexSafely($table, ['payment_date'], 'payments_payment_date_idx');
            
            // Method-based filtering
            $this->addIndexSafely($table, ['method', 'status'], 'payments_method_status_idx');
            
            // Reconciliation dashboard
            $this->addIndexSafely($table, ['reconciliation_status', 'payment_date'], 'payments_recon_date_idx');
            
            // Transaction reference lookups
            if (Schema::hasColumn('payments', 'reference')) {
                $this->addIndexSafely($table, ['reference'], 'payments_reference_idx');
            }
        });

        // PAYMENT_ALLOCATIONS TABLE INDEXES
        if (Schema::hasTable('payment_allocations')) {
            Schema::table('payment_allocations', function (Blueprint $table) {
                // Payment to bill allocations
                $this->addIndexSafely($table, ['payment_id', 'billing_id'], 'payment_alloc_payment_billing_idx');
                
                // Date-based allocation queries
                $this->addIndexSafely($table, ['allocation_date'], 'payment_alloc_date_idx');
            });
        }

        // METERS TABLE INDEXES
        Schema::table('meters', function (Blueprint $table) {
            // Meter number lookups (critical for reading entry)
            $this->addIndexSafely($table, ['meter_number'], 'meters_meter_number_idx');
            
            // Bulk meter hierarchy queries
            if (Schema::hasColumn('meters', 'parent_meter_id')) {
                $this->addIndexSafely($table, ['parent_meter_id'], 'meters_parent_meter_idx');
            }
            
            // Active meters by type
            if (Schema::hasColumn('meters', 'meter_type')) {
                $this->addIndexSafely($table, ['meter_type', 'status'], 'meters_type_status_idx');
            }
        });

        // METER_READINGS TABLE INDEXES
        if (Schema::hasTable('meter_readings')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                // Consumption calculations (latest reading per meter)
                $this->addIndexSafely($table, ['meter_id', 'reading_date'], 'meter_readings_meter_date_idx');
                
                // Reading date range queries
                $this->addIndexSafely($table, ['reading_date'], 'meter_readings_date_idx');
                
                // Reading type filtering (actual vs estimated)
                if (Schema::hasColumn('meter_readings', 'reading_type')) {
                    $this->addIndexSafely($table, ['reading_type'], 'meter_readings_type_idx');
                }
            });
        }

        // CARRY_FORWARD_BALANCES TABLE INDEXES
        if (Schema::hasTable('carry_forward_balances')) {
            Schema::table('carry_forward_balances', function (Blueprint $table) {
                // Active credit lookups (most common)
                $this->addIndexSafely($table, ['account_id', 'type', 'status'], 'carry_forward_account_type_status_idx');
                
                // Expiry monitoring
                $this->addIndexSafely($table, ['expires_at', 'status'], 'carry_forward_expires_status_idx');
            });
        }

        // ACCOUNTS TABLE INDEXES
        if (Schema::hasTable('accounts')) {
            Schema::table('accounts', function (Blueprint $table) {
                // Account number lookups (critical)
                $this->addIndexSafely($table, ['account_number'], 'accounts_account_number_idx');
                
                // Email lookups (for notifications)
                $this->addIndexSafely($table, ['email'], 'accounts_email_idx');
                
                // Active account filtering
                $this->addIndexSafely($table, ['status'], 'accounts_status_idx');
            });
        }

        // TARIFFS TABLE INDEXES
        if (Schema::hasTable('tariffs')) {
            Schema::table('tariffs', function (Blueprint $table) {
                // Tariff code lookups
                $this->addIndexSafely($table, ['code'], 'tariffs_code_idx');
                
                // Active tariff queries by meter type
                $this->addIndexSafely($table, ['meter_type', 'status', 'effective_from'], 'tariffs_meter_status_effective_idx');
            });
        }

        // TARIFF_RATES TABLE INDEXES
        if (Schema::hasTable('tariff_rates')) {
            Schema::table('tariff_rates', function (Blueprint $table) {
                // Tier lookups during billing calculation
                $this->addIndexSafely($table, ['tariff_id', 'tier_number'], 'tariff_rates_tariff_tier_idx');
                
                // Range-based tier selection
                $this->addIndexSafely($table, ['tariff_id', 'min_units', 'max_units'], 'tariff_rates_tariff_range_idx');
            });
        }

        // RESIDENTS TABLE INDEXES
        if (Schema::hasTable('residents')) {
            Schema::table('residents', function (Blueprint $table) {
                // Name search
                $this->addIndexSafely($table, ['name'], 'residents_name_idx');
                
                // Email lookups
                if (Schema::hasColumn('residents', 'email')) {
                    $this->addIndexSafely($table, ['email'], 'residents_email_idx');
                }
            });
        }
    }

    /**
     * Helper method to safely add indexes
     */
    protected function addIndexSafely(Blueprint $table, array $columns, string $indexName): void
    {
        try {
            // Check if index exists
            $tableName = $table->getTable();
            $indexes = DB::select("SHOW INDEX FROM {$tableName} WHERE Key_name = ?", [$indexName]);
            
            if (empty($indexes)) {
                $table->index($columns, $indexName);
            }
        } catch (\Exception $e) {
            // Index may already exist or column doesn't exist
            // Log but don't fail migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all added indexes
        // WARNING: This will impact performance

        $indexes = [
            'billings' => [
                'billings_status_duedate_idx',
                'billings_account_period_idx',
                'billings_issued_at_idx',
                'billings_status_paid_idx',
            ],
            'billing_details' => [
                'billing_details_billing_idx',
                'billing_details_meter_idx',
            ],
            'payments' => [
                'payments_payment_date_idx',
                'payments_method_status_idx',
                'payments_recon_date_idx',
                'payments_reference_idx',
            ],
            'payment_allocations' => [
                'payment_alloc_payment_billing_idx',
                'payment_alloc_date_idx',
            ],
            'meters' => [
                'meters_meter_number_idx',
                'meters_parent_meter_idx',
                'meters_type_status_idx',
            ],
            'meter_readings' => [
                'meter_readings_meter_date_idx',
                'meter_readings_date_idx',
                'meter_readings_type_idx',
            ],
            'carry_forward_balances' => [
                'carry_forward_account_type_status_idx',
                'carry_forward_expires_status_idx',
            ],
            'accounts' => [
                'accounts_account_number_idx',
                'accounts_email_idx',
                'accounts_status_idx',
            ],
            'tariffs' => [
                'tariffs_code_idx',
                'tariffs_meter_status_effective_idx',
            ],
            'tariff_rates' => [
                'tariff_rates_tariff_tier_idx',
                'tariff_rates_tariff_range_idx',
            ],
            'residents' => [
                'residents_name_idx',
                'residents_email_idx',
            ],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($tableIndexes) {
                    foreach ($tableIndexes as $index) {
                        try {
                            $tableBlueprint->dropIndex($index);
                        } catch (\Exception $e) {
                            // Index may not exist
                        }
                    }
                });
            }
        }
    }
};
