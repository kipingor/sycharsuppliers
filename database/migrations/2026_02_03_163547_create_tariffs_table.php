<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Tariffs Table
 *
 * Tariffs define pricing structures for water billing.
 * Each tariff can have multiple rates (tiered pricing).
 *
 * BUSINESS LOGIC:
 * - Tariffs can be tiered (different prices for consumption brackets)
 * - Tariffs can be flat (single rate for all consumption)
 * - Tariffs can be seasonal (different rates by time of year)
 * - Only one tariff can be default per meter_type
 * - Tariffs have effective date ranges
 *
 * PRODUCTION SAFETY:
 * - Schema::hasTable() guard
 * - All columns have appropriate defaults
 * - Indexes for common queries
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tariffs')) {
            Schema::create('tariffs', function (Blueprint $table) {
                $table->id();
                
                // Tariff identification
                $table->string('name')->index();
                $table->string('code', 50)->unique();
                
                // Tariff configuration
                $table->enum('type', ['tiered', 'flat', 'seasonal'])
                    ->default('tiered')
                    ->index();
                
                $table->enum('meter_type', ['individual', 'bulk'])
                    ->default('individual')
                    ->index();
                
                // Status and activation
                $table->enum('status', ['active', 'inactive', 'archived'])
                    ->default('active')
                    ->index();
                
                $table->boolean('is_default')->default(false)->index();
                
                // Effective date range
                $table->date('effective_from')->index();
                $table->date('effective_to')->nullable()->index();
                
                // Description
                $table->text('description')->nullable();
                
                // Standard timestamps
                $table->timestamps();
                $table->softDeletes();
            });

            // Composite indexes for common queries
            Schema::table('tariffs', function (Blueprint $table) {
                // Find active tariff for meter type
                $table->index(['meter_type', 'status', 'effective_from'], 'tariffs_meter_status_effective_idx');
                
                // Find default tariff
                $table->index(['is_default', 'meter_type', 'status'], 'tariffs_default_meter_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PRODUCTION WARNING: Check for tariff_rates before dropping
        Schema::dropIfExists('tariffs');
    }
};
