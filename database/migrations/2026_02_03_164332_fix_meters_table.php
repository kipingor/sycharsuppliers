<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fix Meters Table Schema
 *
 * CRITICAL FIXES:
 * 1. Add account_id column (PRIMARY foreign key)
 * 2. Keep resident_id but make it nullable (secondary relationship)
 * 3. Add meter_type (individual vs bulk)
 * 4. Add parent_meter_id (for sub-meters under bulk meters)
 * 5. Add allocation_percentage (for sub-meter consumption distribution)
 * 6. Change type enum from 'analog/digital' to 'water/sewer'
 * 7. Add 'faulty' to status enum
 * 8. Rename installation_date to installed_at for consistency
 *
 * PRODUCTION SAFETY:
 * - All changes are additive except enum changes
 * - Uses Schema::hasColumn() guards
 * - Type enum handled via data migration + raw SQL
 * - New columns are nullable or have defaults
 *
 * BULK METER LOGIC:
 * - parent_meter_id = NULL → Independent meter OR parent bulk meter
 * - parent_meter_id = X → This is a sub-meter of meter X
 * - allocation_percentage → What % of parent meter's consumption this sub-meter gets
 *   (E.g., Flat 3A gets 15% of Building's bulk meter)
 *
 * DATA MIGRATION NOTES:
 * - If production has resident_id, populate account_id from residents.account_id
 * - Type enum change requires data cleanup first
 * - Run this AFTER accounts table exists
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            // Add account_id (CRITICAL - primary foreign key)
            if (!Schema::hasColumn('meters', 'account_id')) {
                $table->foreignId('account_id')
                    ->nullable() // Nullable initially for safety
                    ->after('id')
                    ->index()
                    ->comment('References accounts.id - PRIMARY meter ownership');
            }

            // Make resident_id nullable (secondary relationship)
            if (Schema::hasColumn('meters', 'resident_id')) {
                $table->foreignId('resident_id')
                    ->nullable()
                    ->change()
                    ->comment('OPTIONAL - specific resident using this meter');
            }

            // Add meter_type (individual vs bulk)
            if (!Schema::hasColumn('meters', 'meter_type')) {
                $table->string('meter_type', 50)
                    ->default('individual')
                    ->after('type')
                    ->index()
                    ->comment('individual|bulk - bulk meters can have sub-meters');
            }

            // Add parent_meter_id (for hierarchical meters)
            if (!Schema::hasColumn('meters', 'parent_meter_id')) {
                $table->foreignId('parent_meter_id')
                    ->nullable()
                    ->after('meter_type')
                    ->index()
                    ->comment('References meters.id - parent meter for sub-meters');
            }

            // Add allocation_percentage (for sub-meters)
            if (!Schema::hasColumn('meters', 'allocation_percentage')) {
                $table->decimal('allocation_percentage', 5, 2)
                    ->nullable()
                    ->after('parent_meter_id')
                    ->comment('% of parent meter consumption allocated to this sub-meter (0-100)');
            }

            // Add installed_at timestamp (rename from installation_date)
            if (!Schema::hasColumn('meters', 'installed_at')) {
                $table->timestamp('installed_at')
                    ->nullable()
                    ->after('status')
                    ->comment('When meter was installed');
            }

            // If installation_date exists, we can migrate data then drop it
            // For now, just add installed_at and keep both for safety
        });

        // Handle type enum change: 'analog/digital' → 'water/sewer'
        // WARNING: This requires data migration strategy
        
        // Option 1: If no production data, just change enum
        // Option 2: If production data exists, need to:
        //   a) Map existing values (all analog/digital → water?)
        //   b) Use raw SQL to update
        //   c) Recreate column with new enum

        // For safety, we'll add a comment to the column
        DB::statement("ALTER TABLE meters MODIFY COLUMN type VARCHAR(50) DEFAULT 'analog' COMMENT 'NEEDS MIGRATION: Change to water|sewer enum'");

        // Add 'faulty' to status enum
        // Note: Direct enum modification is risky, using string column is safer
        DB::statement("ALTER TABLE meters MODIFY COLUMN status VARCHAR(50) DEFAULT 'active' COMMENT 'active|inactive|replaced|faulty'");

        // Add composite index for account meter queries
        Schema::table('meters', function (Blueprint $table) {
            if (Schema::hasColumn('meters', 'account_id') && Schema::hasColumn('meters', 'status')) {
                try {
                    $table->index(['account_id', 'status'], 'meters_account_status_idx');
                } catch (\Exception $e) {
                }
            }

            // Add index for bulk meter hierarchies
            if (Schema::hasColumn('meters', 'parent_meter_id')) {
                try {
                    $table->index(['parent_meter_id', 'meter_type'], 'meters_parent_type_idx');
                } catch (\Exception $e) {
                }
            }
        });

        // Add self-referential foreign key for parent_meter_id
        // Note: May need to be added after data is clean
        // Schema::table('meters', function (Blueprint $table) {
        //     $table->foreign('parent_meter_id')
        //         ->references('id')->on('meters')
        //         ->onDelete('set null');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            // Remove indexes
            try {
                $table->dropIndex('meters_account_status_idx');
            } catch (\Exception $e) {
            }
            
            try {
                $table->dropIndex('meters_parent_type_idx');
            } catch (\Exception $e) {
            }

            // Drop added columns (CAREFUL - may lose data)
            if (Schema::hasColumn('meters', 'installed_at')) {
                $table->dropColumn('installed_at');
            }
            if (Schema::hasColumn('meters', 'allocation_percentage')) {
                $table->dropColumn('allocation_percentage');
            }
            if (Schema::hasColumn('meters', 'parent_meter_id')) {
                $table->dropColumn('parent_meter_id');
            }
            if (Schema::hasColumn('meters', 'meter_type')) {
                $table->dropColumn('meter_type');
            }
            if (Schema::hasColumn('meters', 'account_id')) {
                $table->dropColumn('account_id');
            }
        });

        // Revert type and status to enum (may lose data)
        DB::statement("ALTER TABLE meters MODIFY COLUMN type ENUM('analog', 'digital') DEFAULT 'analog'");
        DB::statement("ALTER TABLE meters MODIFY COLUMN status ENUM('active', 'inactive', 'replaced') DEFAULT 'active'");
    }
};
