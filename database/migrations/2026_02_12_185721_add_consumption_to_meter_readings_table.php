<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

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

        if (Schema::hasColumn('meter_readings', 'employee_id') && !Schema::hasColumn('meter_readings', 'reader_id')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->renameColumn('employee_id', 'reader_id');
            });
        }

        if ($driver === 'mysql') {
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

            DB::statement("
                UPDATE meter_readings mr
                INNER JOIN temp_consumption tc ON mr.id = tc.id
                SET mr.consumption = tc.consumption_value
            ");

            DB::statement("DROP TEMPORARY TABLE IF EXISTS temp_consumption");
        } else {
            $readings = DB::table('meter_readings')
                ->select('id', 'meter_id', 'reading_date', 'reading_value')
                ->orderBy('meter_id')
                ->orderBy('reading_date')
                ->get()
                ->groupBy('meter_id');

            foreach ($readings as $meterReadings) {
                $previous = null;

                foreach ($meterReadings as $reading) {
                    $consumption = $previous
                        ? max(0, (float) $reading->reading_value - (float) $previous->reading_value)
                        : 0;

                    DB::table('meter_readings')
                        ->where('id', $reading->id)
                        ->update(['consumption' => $consumption]);

                    $previous = $reading;
                }
            }
        }
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
