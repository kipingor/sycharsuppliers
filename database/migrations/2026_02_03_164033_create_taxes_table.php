<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Taxes Table
 *
 * Defines tax rates applicable to billing (VAT, service tax, etc.)
 *
 * BUSINESS LOGIC:
 * - Taxes can be percentage-based or fixed amount
 * - Taxes have effective date ranges
 * - Multiple taxes can be active simultaneously
 * - Tax rates may change over time (keep historical records)
 *
 * PRODUCTION SAFETY:
 * - Schema::hasTable() guard
 * - Indexes for active tax lookups
 * - Soft deletes for historical record keeping
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('taxes')) {
            Schema::create('taxes', function (Blueprint $table) {
                $table->id();
                
                // Tax identification
                $table->string('name')->index()
                    ->comment('E.g., "VAT", "Service Tax"');
                
                $table->string('code', 50)->unique()
                    ->comment('Short code for tax type');
                
                // Tax configuration
                $table->enum('type', ['percentage', 'fixed'])
                    ->default('percentage')
                    ->comment('percentage = % of amount, fixed = flat fee');
                
                $table->decimal('rate', 5, 2)
                    ->comment('For percentage: 16.00 = 16%, For fixed: amount in currency');
                
                // Status and activation
                $table->enum('status', ['active', 'inactive'])
                    ->default('active')
                    ->index();
                
                // Effective date range
                $table->date('effective_from')->index();
                $table->date('effective_to')->nullable()->index();
                
                // Description
                $table->text('description')->nullable();
                
                // Standard timestamps
                $table->timestamps();
                $table->softDeletes();
            });

            // Composite index for active tax lookups
            Schema::table('taxes', function (Blueprint $table) {
                $table->index(
                    ['status', 'effective_from', 'effective_to'],
                    'taxes_status_effective_idx'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
