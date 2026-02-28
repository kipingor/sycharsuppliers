<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Tariff Rates Table
 *
 * Tariff rates define the pricing tiers within a tariff.
 * Example: Tier 1 (0-10 units) = 50 KES/unit
 *          Tier 2 (11-50 units) = 75 KES/unit
 *          Tier 3 (51+ units) = 100 KES/unit
 *
 * CRITICAL PRECISION:
 * - rate_per_unit uses decimal(10,4) for precision (e.g., 12.3456)
 * - fixed_charge uses decimal(10,2) for currency (e.g., 150.00)
 * - min_units and max_units use decimal(10,2) for fractional units
 *
 * PRODUCTION SAFETY:
 * - Schema::hasTable() guard
 * - Unique constraint on tariff_id + tier_number
 * - Indexes for billing calculations
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tariff_rates')) {
            Schema::create('tariff_rates', function (Blueprint $table) {
                $table->id();
                
                // Foreign key to tariffs
                $table->foreignId('tariff_id')
                    ->index()
                    ->comment('References tariffs.id');
                
                // Tier configuration
                $table->integer('tier_number')->unsigned();
                
                // Consumption range
                $table->decimal('min_units', 10, 2)->default(0);
                $table->decimal('max_units', 10, 2)->nullable()
                    ->comment('NULL = unlimited');
                
                // Pricing - CRITICAL PRECISION
                $table->decimal('rate_per_unit', 10, 4)
                    ->comment('Price per unit (4 decimals for precision)');
                
                $table->decimal('fixed_charge', 10, 2)->default(0)
                    ->comment('Fixed charge for this tier');
                
                // Standard timestamps
                $table->timestamps();
                
                // Unique constraint: one tier number per tariff
                $table->unique(['tariff_id', 'tier_number'], 'tariff_rates_tariff_tier_unique');
            });

            // Index for fast tier lookups during billing
            Schema::table('tariff_rates', function (Blueprint $table) {
                $table->index(['tariff_id', 'min_units', 'max_units'], 'tariff_rates_range_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_rates');
    }
};
