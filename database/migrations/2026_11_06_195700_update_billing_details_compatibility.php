<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->setReaderForeignKey('users');

        Schema::table('billing_details', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_details', 'reading_date')) {
                $table->date('reading_date')->nullable()->after('meter_id');
            }

            if (!Schema::hasColumn('billing_details', 'consumption')) {
                $table->decimal('consumption', 12, 2)->nullable()->after('reading_date');
            }
        });

        if (Schema::hasColumn('billing_details', 'consumption')
            && Schema::hasColumn('billing_details', 'units_used')
            && Schema::hasColumn('billing_details', 'current_reading_value')
            && Schema::hasColumn('billing_details', 'previous_reading_value')) {
            DB::statement('UPDATE billing_details
                SET consumption = CASE
                    WHEN consumption IS NULL
                    THEN COALESCE(
                        CAST(units_used AS DECIMAL(12,2)),
                        CAST(current_reading_value AS DECIMAL(12,2)) - CAST(previous_reading_value AS DECIMAL(12,2))
                    )
                    ELSE consumption
                END
                WHERE consumption IS NULL');
        }
    }

    private function setReaderForeignKey(string $referencedTable): void
    {
        if (!Schema::hasTable('meter_readings') || !Schema::hasColumn('meter_readings', 'reader_id')) {
            return;
        }

        $readerForeignKeys = collect(Schema::getForeignKeys('meter_readings'))
            ->filter(fn (array $foreignKey) => in_array('reader_id', $foreignKey['columns'], true));

        $hasExpectedForeignKey = $readerForeignKeys->contains(
            fn (array $foreignKey) => $foreignKey['foreign_table'] === $referencedTable
                && $foreignKey['foreign_columns'] === ['id']
                && strtolower($foreignKey['on_delete']) === 'set null'
        );

        if ($hasExpectedForeignKey) {
            return;
        }

        Schema::table('meter_readings', function (Blueprint $table) use ($readerForeignKeys, $referencedTable) {
            foreach ($readerForeignKeys as $foreignKey) {
                $table->dropForeign($foreignKey['name'] ?? ['reader_id']);
            }

            $table->foreign('reader_id')
                ->references('id')
                ->on($referencedTable)
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->setReaderForeignKey('employees');

        Schema::table('billing_details', function (Blueprint $table) {
            if (Schema::hasColumn('billing_details', 'consumption')) {
                $table->dropColumn('consumption');
            }

            if (Schema::hasColumn('billing_details', 'reading_date')) {
                $table->dropColumn('reading_date');
            }
        });
    }
};
