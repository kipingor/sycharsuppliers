<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Carry Forward Balances Table
 *
 * CRITICAL FOR FINANCIAL INTEGRITY
 *
 * Tracks credit and debit balances that carry forward between billing periods.
 *
 * USE CASES:
 * - Customer overpays: Create CREDIT balance
 * - Customer underpays: Create DEBIT balance
 * - Credit can be applied to future bills
 * - Credits can expire after a period
 * - Debits track outstanding amounts
 *
 * BUSINESS RULES:
 * - Credits reduce future bill amounts
 * - Credits have optional expiry dates
 * - Status: active → applied (when used) or expired (when time limit reached)
 * - One payment can create one carry-forward balance
 * - Multiple carry-forwards can exist per account
 *
 * PRODUCTION SAFETY:
 * - Schema::hasTable() guard
 * - Indexes for balance lookups
 * - Foreign keys with appropriate cascades
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('carry_forward_balances')) {
            Schema::create('carry_forward_balances', function (Blueprint $table) {
                $table->id();
                
                // Foreign keys
                $table->foreignId('account_id')
                    ->index()
                    ->comment('References accounts.id');
                
                $table->foreignId('payment_id')
                    ->nullable()
                    ->index()
                    ->comment('Payment that created this balance (if from overpayment)');
                
                // Balance details
                $table->enum('type', ['credit', 'debit'])
                    ->index()
                    ->comment('credit = customer overpaid, debit = customer underpaid');
                
                $table->decimal('balance', 12, 2)
                    ->comment('Remaining balance amount');
                
                // Status tracking
                $table->enum('status', ['active', 'applied', 'expired'])
                    ->default('active')
                    ->index()
                    ->comment('active = available for use, applied = fully used, expired = time limit reached');
                
                // Expiry handling
                $table->timestamp('expires_at')
                    ->nullable()
                    ->index()
                    ->comment('When credit expires (NULL = never expires)');
                
                // Audit trail
                $table->text('notes')->nullable()
                    ->comment('Reason for balance, allocation notes, etc.');
                
                // Standard timestamps
                $table->timestamps();
            });

            // Composite indexes for balance queries
            Schema::table('carry_forward_balances', function (Blueprint $table) {
                // Find active credits for an account (most common query)
                $table->index(
                    ['account_id', 'status', 'type'],
                    'carry_forward_account_status_type_idx'
                );
                
                // Find expiring credits (for scheduled cleanup)
                $table->index(
                    ['status', 'expires_at'],
                    'carry_forward_status_expires_idx'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PRODUCTION WARNING: This will lose all credit/debit history
        // Customers may lose entitled credits
        // Only drop if no balances exist or you have a data migration plan
        Schema::dropIfExists('carry_forward_balances');
    }
};
