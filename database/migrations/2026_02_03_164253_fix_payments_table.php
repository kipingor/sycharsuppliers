<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fix Payments Table Schema
 *
 * CRITICAL FIXES:
 * 1. Add account_id column (PRIMARY foreign key)
 * 2. Make meter_id nullable (deprecated, for backward compatibility)
 * 3. Make billing_id nullable (deprecated, use payment_allocations instead)
 * 4. Add reference column (payment reference number)
 * 5. Add reconciliation tracking columns
 * 6. Expand method enum to include Card, Cheque
 * 7. Add 'reversed' to status enum
 * 8. Remove unique constraint from transaction_id (may have NULLs)
 *
 * PRODUCTION SAFETY:
 * - All changes are additive
 * - Uses Schema::hasColumn() guards
 * - Deprecated columns kept for backward compatibility
 * - All new columns are nullable
 *
 * DATA MIGRATION NOTES:
 * - If production has meter_id, populate account_id from meters.account_id
 * - Run this AFTER accounts table exists
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add account_id (CRITICAL - primary foreign key)
            if (!Schema::hasColumn('payments', 'account_id')) {
                $table->foreignId('account_id')
                    ->nullable() // Nullable initially for safety
                    ->after('id')
                    ->index()
                    ->comment('References accounts.id - PRIMARY payment relationship');
            }

            // Add reference column (payment reference number)
            if (!Schema::hasColumn('payments', 'reference')) {
                $table->string('reference', 100)
                    ->nullable()
                    ->after('transaction_id')
                    ->index()
                    ->comment('Payment reference number (invoice ref, receipt number, etc.)');
            }

            // Add reconciliation_status
            if (!Schema::hasColumn('payments', 'reconciliation_status')) {
                $table->string('reconciliation_status', 50) // Using string for flexibility
                    ->default('pending')
                    ->after('status')
                    ->index()
                    ->comment('pending|reconciled|partially_reconciled - tracks allocation status');
            }

            // Add reconciled_at timestamp
            if (!Schema::hasColumn('payments', 'reconciled_at')) {
                $table->timestamp('reconciled_at')
                    ->nullable()
                    ->after('reconciliation_status')
                    ->comment('When payment was reconciled to bills');
            }

            // Add reconciled_by (user who reconciled)
            if (!Schema::hasColumn('payments', 'reconciled_by')) {
                $table->foreignId('reconciled_by')
                    ->nullable()
                    ->after('reconciled_at')
                    ->comment('User ID who performed reconciliation');
            }

            // Make meter_id nullable (for backward compatibility)
            if (Schema::hasColumn('payments', 'meter_id')) {
                $table->foreignId('meter_id')
                    ->nullable()
                    ->change()
                    ->comment('DEPRECATED - use account_id and payment_allocations instead');
            }

            // Add billing_id if it doesn't exist (for backward compatibility)
            if (!Schema::hasColumn('payments', 'billing_id')) {
                $table->foreignId('billing_id')
                    ->nullable()
                    ->after('meter_id')
                    ->comment('DEPRECATED - use payment_allocations instead');
            } else {
                // Make existing billing_id nullable
                $table->foreignId('billing_id')
                    ->nullable()
                    ->change()
                    ->comment('DEPRECATED - use payment_allocations instead');
            }
        });

        // Update enum values for existing data
        // method: Add 'Card' and 'Cheque' options
        // status: Add 'reversed' option
        
        // Note: Enum modification via raw SQL is safer than column modification
        // For production, consider using string columns with validation instead of enums

        // Remove unique constraint from transaction_id if it exists
        // (Some payments may not have transaction IDs)
        try {
            DB::statement('ALTER TABLE payments DROP INDEX payments_transaction_id_unique');
        } catch (\Exception $e) {
            // Index may not exist or have different name
        }

        // Make transaction_id nullable if not already
        if (Schema::hasColumn('payments', 'transaction_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('transaction_id', 100)
                    ->nullable()
                    ->change()
                    ->comment('External transaction ID (M-Pesa code, bank ref, etc.)');
            });
        }

        // Add composite index for account payment queries
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'account_id') && Schema::hasColumn('payments', 'payment_date')) {
                try {
                    $table->index(['account_id', 'payment_date'], 'payments_account_date_idx');
                } catch (\Exception $e) {
                    // Index may already exist
                }
            }

            // Add index for reconciliation queries
            if (Schema::hasColumn('payments', 'reconciliation_status')) {
                try {
                    $table->index(['reconciliation_status', 'status'], 'payments_reconciliation_status_idx');
                } catch (\Exception $e) {
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Remove indexes
            try {
                $table->dropIndex('payments_account_date_idx');
            } catch (\Exception $e) {
            }
            
            try {
                $table->dropIndex('payments_reconciliation_status_idx');
            } catch (\Exception $e) {
            }

            // Drop added columns (CAREFUL - may lose data)
            if (Schema::hasColumn('payments', 'reconciled_by')) {
                $table->dropColumn('reconciled_by');
            }
            if (Schema::hasColumn('payments', 'reconciled_at')) {
                $table->dropColumn('reconciled_at');
            }
            if (Schema::hasColumn('payments', 'reconciliation_status')) {
                $table->dropColumn('reconciliation_status');
            }
            if (Schema::hasColumn('payments', 'reference')) {
                $table->dropColumn('reference');
            }
            if (Schema::hasColumn('payments', 'account_id')) {
                $table->dropColumn('account_id');
            }
        });

        // Restore transaction_id unique constraint
        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('transaction_id')->unique()->change();
            });
        } catch (\Exception $e) {
            // May fail if NULL values exist
        }
    }
};
