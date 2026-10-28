<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('meters', 'replaced_by_meter_id')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->foreignId('replaced_by_meter_id')
                    ->nullable()
                    ->unique()
                    ->constrained('meters')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meters', 'replaced_by_meter_id')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->dropForeign(['replaced_by_meter_id']);
                $table->dropUnique(['replaced_by_meter_id']);
                $table->dropColumn('replaced_by_meter_id');
            });
        }
    }
};
