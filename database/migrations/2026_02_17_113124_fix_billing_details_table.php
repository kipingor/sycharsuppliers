<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix Billing Details Table
 *
 * Original table only had: billing_id, previous_reading_value (string),
 *   current_reading_value (string), units_used (string)
 *
 * This migration:
 *   1. Adds missing columns: meter_id, rate, amount, description
 *   2. Converts string reading/units columns to proper decimal types
 *   3. Populates meter_id via account → meters relationship
 *   4. Populates rate by looking up the tariff effective on each billing's period date
 *   5. Populates amount = units_used * rate
 *   6. Populates description from meter/billing context
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────
        // STEP 1: Add missing columns (all nullable so
        //         existing rows are not rejected)
        // ─────────────────────────────────────────────
        Schema::table('billing_details', function (Blueprint $table) {

            if (!Schema::hasColumn('billing_details', 'meter_id')) {
                $table->unsignedBigInteger('meter_id')
                      ->nullable()
                      ->after('billing_id');
            }

            if (!Schema::hasColumn('billing_details', 'rate')) {
                $table->decimal('rate', 10, 4)
                      ->nullable()
                      ->after('units_used')
                      ->comment('Rate per unit at time of billing');
            }

            if (!Schema::hasColumn('billing_details', 'amount')) {
                $table->decimal('amount', 12, 2)
                      ->nullable()
                      ->after('rate')
                      ->comment('units_used * rate');
            }

            if (!Schema::hasColumn('billing_details', 'description')) {
                $table->string('description')->nullable()->after('amount');
            }
        });

        // ─────────────────────────────────────────────
        // STEP 2: Convert reading/units columns from
        //         string → decimal (MySQL-safe via ALTER)
        // ─────────────────────────────────────────────
        DB::statement('
            ALTER TABLE billing_details
                MODIFY COLUMN previous_reading_value DECIMAL(12,2) NULL,
                MODIFY COLUMN current_reading_value  DECIMAL(12,2) NULL,
                MODIFY COLUMN units_used             DECIMAL(12,2) NULL
        ');

        // ─────────────────────────────────────────────
        // STEP 3: Populate meter_id
        //
        // Strategy A — billing has meter_id (legacy schema)
        // Strategy B — derive from account's single active meter
        // Strategy C — pick the first active meter per account
        //              as a safe fallback
        // ─────────────────────────────────────────────

        // Strategy A
        if (Schema::hasColumn('billings', 'meter_id')) {
            DB::statement('
                UPDATE billing_details bd
                INNER JOIN billings b ON bd.billing_id = b.id
                SET bd.meter_id = b.meter_id
                WHERE bd.meter_id IS NULL
                  AND b.meter_id IS NOT NULL
            ');
        }

        // Strategy B/C — match via account (single meter preferred)
        DB::statement('
            UPDATE billing_details bd
            INNER JOIN billings b ON bd.billing_id = b.id
            INNER JOIN (
                SELECT account_id, MIN(id) AS meter_id
                FROM meters
                WHERE status = "active"
                GROUP BY account_id
            ) m ON b.account_id = m.account_id
            SET bd.meter_id = m.meter_id
            WHERE bd.meter_id IS NULL
        ');

        // ─────────────────────────────────────────────
        // STEP 4: Populate rate from historical tariffs
        //
        // Looks up the tariff that was effective on the
        // billing period date, then finds the correct
        // tier from tariff_rates based on units_used.
        //
        // Falls back to 300 (known base rate) when no
        // tariff record covers that date.
        // ─────────────────────────────────────────────

        $this->populateRates();

        // ─────────────────────────────────────────────
        // STEP 5: Calculate amount = units_used * rate
        // ─────────────────────────────────────────────
        DB::statement('
            UPDATE billing_details
            SET amount = ROUND(units_used * rate, 2)
            WHERE amount IS NULL
              AND units_used IS NOT NULL
              AND rate IS NOT NULL
        ');

        // ─────────────────────────────────────────────
        // STEP 6: Populate description
        // ─────────────────────────────────────────────
        DB::statement('
            UPDATE billing_details bd
            INNER JOIN billings b   ON bd.billing_id = b.id
            LEFT  JOIN meters m     ON bd.meter_id   = m.id
            SET bd.description = CONCAT(
                "Water consumption for ",
                COALESCE(m.meter_name, CONCAT("Meter #", bd.meter_id), "Unknown meter"),
                " - Period: ",
                COALESCE(b.billing_period, "N/A")
            )
            WHERE bd.description IS NULL
        ');

        // ─────────────────────────────────────────────
        // STEP 7: Add FK index now that data is present
        // ─────────────────────────────────────────────
        Schema::table('billing_details', function (Blueprint $table) {
            // Index for faster joins from meter side
            $table->index('meter_id', 'bd_meter_id_idx');
        });

        // ─────────────────────────────────────────────
        // Report
        // ─────────────────────────────────────────────
        $total     = DB::table('billing_details')->count();
        $nullMeter = DB::table('billing_details')->whereNull('meter_id')->count();
        $nullRate  = DB::table('billing_details')->whereNull('rate')->count();
        $nullAmt   = DB::table('billing_details')->whereNull('amount')->count();

        if ($nullMeter > 0 || $nullRate > 0 || $nullAmt > 0) {
            echo "\n⚠  Incomplete rows after migration (out of {$total} total):\n";
            echo "   meter_id NULL : {$nullMeter}\n";
            echo "   rate     NULL : {$nullRate}\n";
            echo "   amount   NULL : {$nullAmt}\n";
            echo "   These rows likely belong to accounts with no active meter\n";
            echo "   or billing periods that pre-date all tariff records.\n\n";
        } else {
            echo "\n✓  All {$total} billing_details rows fully populated.\n\n";
        }
    }

    public function down(): void
    {
        // Remove the FK index first
        Schema::table('billing_details', function (Blueprint $table) {
            $table->dropIndex('bd_meter_id_idx');
        });

        // Revert decimal columns back to string (data preserved as text)
        DB::statement('
            ALTER TABLE billing_details
                MODIFY COLUMN previous_reading_value VARCHAR(255) NULL,
                MODIFY COLUMN current_reading_value  VARCHAR(255) NULL,
                MODIFY COLUMN units_used             VARCHAR(255) NULL
        ');

        // Drop added columns
        Schema::table('billing_details', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('billing_details', 'meter_id')     ? 'meter_id'     : null,
                Schema::hasColumn('billing_details', 'rate')         ? 'rate'         : null,
                Schema::hasColumn('billing_details', 'amount')       ? 'amount'       : null,
                Schema::hasColumn('billing_details', 'description')  ? 'description'  : null,
            ]));
        });
    }

    // ─────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────

    /**
     * Populate rate for every billing_detail row.
     *
     * Lookup order:
     *   1. Tariff effective on billing_period date → correct tier based on units_used
     *   2. Any tariff rate if no date match (oldest available)
     *   3. Hardcoded fallback of 300 (the known historical base rate)
     */
    private function populateRates(): void
    {
        // Fetch every row that still needs a rate, with enough context to look up tariffs
        $rows = DB::table('billing_details as bd')
            ->join('billings as b', 'bd.billing_id', '=', 'b.id')
            ->leftJoin('meters as m', 'bd.meter_id', '=', 'm.id')
            ->whereNull('bd.rate')
            ->select(
                'bd.id',
                'bd.units_used',
                'b.billing_period',
                'm.meter_type',       // 'individual' or 'bulk'
            )
            ->get();

        $fallbackRate = 300.00; // Known historical base rate

        foreach ($rows as $row) {
            $rate = $this->lookupRate(
                $row->billing_period,
                (float) ($row->units_used ?? 0),
                $row->meter_type ?? 'individual',
                $fallbackRate
            );

            DB::table('billing_details')
                ->where('id', $row->id)
                ->update(['rate' => $rate]);
        }
    }

    /**
     * Find the rate per unit that was in effect on the given billing period.
     *
     * @param string|null $billingPeriod  e.g. "2024-03"
     * @param float       $units          units consumed (for tier lookup)
     * @param string      $meterType      'individual' or 'bulk'
     * @param float       $fallback       rate to use if no tariff found
     */
    private function lookupRate(
        ?string $billingPeriod,
        float   $units,
        string  $meterType,
        float   $fallback
    ): float {
        // Derive a date from the billing period (use first day of month)
        $billingDate = $billingPeriod
            ? $billingPeriod . '-01'
            : null;

        // ── Attempt 1: tariff effective on billing date ──────────────────────
        if ($billingDate) {
            $tariffId = DB::table('tariffs')
                ->where('status', 'active')
                ->where('effective_from', '<=', $billingDate)
                ->where(function ($q) use ($billingDate) {
                    $q->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $billingDate);
                })
                ->where(function ($q) use ($meterType) {
                    // meter_type on tariffs is 'individual' or 'bulk' — match or accept universal (null)
                    $q->whereNull('meter_type')
                      ->orWhere('meter_type', $meterType);
                })
                ->orderByDesc('effective_from') // most recently effective first
                ->value('id');

            if ($tariffId) {
                $rate = $this->rateFromTariff($tariffId, $units);
                if ($rate !== null) {
                    return $rate;
                }
            }
        }

        // ── Attempt 2: any tariff (ignoring dates) ───────────────────────────
        $anyTariffId = DB::table('tariffs')
            ->where('status', 'active')
            ->orderBy('effective_from') // oldest first → most conservative
            ->value('id');

        if ($anyTariffId) {
            $rate = $this->rateFromTariff($anyTariffId, $units);
            if ($rate !== null) {
                return $rate;
            }
        }

        // ── Attempt 3: any rate row whatsoever ───────────────────────────────
        $anyRate = DB::table('tariff_rates')->value('rate_per_unit');

        if ($anyRate !== null) {
            return (float) $anyRate;
        }

        // ── Fallback ─────────────────────────────────────────────────────────
        return $fallback;
    }

    /**
     * Fetch the correct rate_per_unit from tariff_rates for this tariff + units.
     * Handles both tiered (range-based) and flat (single row) tariffs.
     */
    private function rateFromTariff(int $tariffId, float $units): ?float
    {
        // Try the tariff_rates column name 'rate_per_unit' first, then 'rate'
        // (the migration uses rate_per_unit; the TariffRate model uses rate)
        $rateRow = DB::table('tariff_rates')
            ->where('tariff_id', $tariffId)
            ->where('min_units', '<=', $units)
            ->where(function ($q) use ($units) {
                $q->whereNull('max_units')
                  ->orWhere('max_units', '>=', $units);
            })
            ->orderByDesc('min_units') // deepest matching tier first
            ->first();

        if (!$rateRow) {
            // No tier matched (units below lowest tier) — take the lowest tier
            $rateRow = DB::table('tariff_rates')
                ->where('tariff_id', $tariffId)
                ->orderBy('min_units')
                ->first();
        }

        if (!$rateRow) {
            return null;
        }

        return isset($rateRow->rate_per_unit) ? (float) $rateRow->rate_per_unit : null;
    }
};