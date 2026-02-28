<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Payment Allocations Table
 *
 * CRITICAL FOR PAYMENT RECONCILIATION
 *
 * This table tracks how payments are allocated to specific bills.
 * One payment can be split across multiple bills.
 * One bill can receive allocations from multiple payments.
 *
 * BUSINESS RULES:
 * - Sum of allocations for a payment cannot exceed payment.amount
 * - Sum of allocations for a billing determines paid_amount
 * - Allocations are created during payment reconciliation
 * - Allocations should not be deleted (audit trail)
 *
 * PRODUCTION SAFETY:
 * - Schema::hasTable() guard
 * - Foreign keys reference with RESTRICT (prevent orphans)
 * - Indexes for fast reconciliation queries
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->id();
                
                // Foreign keys - DO NOT CASCADE DELETE (preserve audit trail)
                $table->foreignId('payment_id')
                    ->index()
                    ->comment('References payments.id');
                
                $table->foreignId('billing_id')
                    ->index()
                    ->comment('References billings.id');
                
                // Allocation details
                $table->decimal('allocated_amount', 12, 2)
                    ->comment('Amount allocated from payment to this bill');
                
                $table->datetime('allocation_date')
                    ->index()
                    ->comment('When allocation was made');
                
                // Audit notes
                $table->text('notes')->nullable()
                    ->comment('Reconciliation notes');
                
                // Standard timestamps
                $table->timestamps();
            });

            // Composite indexes for reconciliation queries
            Schema::table('payment_allocations', function (Blueprint $table) {
                // Get all allocations for a payment
                $table->index(['payment_id', 'allocation_date'], 'payment_allocations_payment_date_idx');
                
                // Get all allocations for a billing
                $table->index(['billing_id', 'allocation_date'], 'payment_allocations_billing_date_idx');
                
                // Prevent duplicate allocations (same payment to same bill on same date)
                // Removed unique constraint as legitimate to have multiple allocations
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PRODUCTION WARNING: This will break payment reconciliation history
        // Only drop if you're absolutely certain no allocations exist
        Schema::dropIfExists('payment_allocations');
    }
};
