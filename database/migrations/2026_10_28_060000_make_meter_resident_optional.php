<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('meters', 'resident_id')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->foreignId('resident_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('meters', 'resident_id')
            && DB::table('meters')->whereNull('resident_id')->exists()
        ) {
            throw new RuntimeException('Cannot require a resident while meters have no linked resident.');
        }

        if (Schema::hasColumn('meters', 'resident_id')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->foreignId('resident_id')->nullable(false)->change();
            });
        }
    }
};
