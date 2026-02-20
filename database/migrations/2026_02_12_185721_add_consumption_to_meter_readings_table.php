<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Step 1: Add the consumption column
        if (!Schema::hasColumn('meter_readings', 'consumption')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->decimal('consumption', 10, 2)->default(0)->after('reading_value');
            });
        }

        if (!Schema::hasColumn('meter_readings', 'notes')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('consumption');
            });
        }

        if (!Schema::hasColumn('meter_readings', 'photo_path')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->string('photo_path')->nullable()->after('notes');
            });
        }

        Schema::table('meter_readings', function (Blueprint $table) {
            $table->renameColumn('employee_id', 'reader_id');
        });

        // Step 2: Create temporary table with calculated consumption
        DB::statement("
            CREATE TEMPORARY TABLE temp_consumption AS
            SELECT 
                mr1.id,
                GREATEST(0, 
                    mr1.reading_value - COALESCE(
                        (
                            SELECT mr2.reading_value 
                            FROM meter_readings mr2 
                            WHERE mr2.meter_id = mr1.meter_id 
                            AND mr2.reading_date < mr1.reading_date 
                            ORDER BY mr2.reading_date DESC 
                            LIMIT 1
                        ), 
                        mr1.reading_value
                    )
                ) AS consumption_value
            FROM meter_readings mr1
        ");

        // Step 3: Update from temporary table
        DB::statement("
            UPDATE meter_readings mr
            INNER JOIN temp_consumption tc ON mr.id = tc.id
            SET mr.consumption = tc.consumption_value
        ");

        // Step 4: Drop temporary table
        DB::statement("DROP TEMPORARY TABLE IF EXISTS temp_consumption");
    }

    public function down(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dropColumn('consumption');
            $table->dropColumn('notes');
            $table->dropColumn('photo_path');
        });
    }
};
