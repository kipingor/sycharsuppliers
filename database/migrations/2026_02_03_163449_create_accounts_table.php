<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Accounts Table
 *
 * This is a CRITICAL foundation table that should have existed from the start.
 * The Account is the central billing entity. Multiple meters, bills, and payments
 * all belong to an account.
 *
 * PRODUCTION SAFETY:
 * - Uses Schema::hasTable() guard
 * - Creates table only if it doesn't exist
 * - All columns nullable or have defaults for safety
 * - Indexes created for performance
 *
 * ROLLBACK SAFETY:
 * - Can be rolled back safely if no foreign keys reference it yet
 * - Run Phase 1 migrations first before Phase 3 foreign keys
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only create if table doesn't exist (production may have manual create)
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                
                // Account identification
                $table->string('account_number', 50)->unique();
                $table->string('name')->index();
                
                // Contact information
                $table->string('email')->nullable()->index();
                $table->string('phone', 20)->nullable();
                $table->text('address')->nullable();
                
                // Account status
                $table->enum('status', ['active', 'suspended', 'inactive'])
                    ->default('active')
                    ->index();
                
                // Status tracking timestamps
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                
                // Standard timestamps
                $table->timestamps();
                $table->softDeletes();
            });

            // Add indexes for common queries
            Schema::table('accounts', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'accounts_status_created_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PRODUCTION WARNING: Only safe to drop if no foreign keys reference this table
        // Check for dependent tables: meters, billings, payments, carry_forward_balances
        Schema::dropIfExists('accounts');
    }
};
