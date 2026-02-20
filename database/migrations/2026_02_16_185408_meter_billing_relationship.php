<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('billing_details', 'meter_id')) {
            Schema::table('billing_details', function (Blueprint $table) {
                $table->foreignId('meter_id')->after('billing_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        // Get the database driver
        $driver = Schema::connection($this->getConnection())->getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            // For MySQL, we need to use raw SQL to modify ENUM
            DB::statement("ALTER TABLE meter_readings MODIFY COLUMN reading_type ENUM('manual', 'automatic', 'actual', 'estimated', 'correction') NOT NULL DEFAULT 'manual'");
            
            // Optional: Update existing values to new nomenclature
            // Uncomment these if you want to migrate data to new values
            DB::table('meter_readings')->where('reading_type', 'manual')->update(['reading_type' => 'actual']);
            DB::table('meter_readings')->where('reading_type', 'automatic')->update(['reading_type' => 'estimated']);
        } else {
            // For PostgreSQL, SQLite, etc., just change the column type to string if it's not already
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->string('reading_type', 20)->default('manual')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::connection($this->getConnection())->getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            // Revert to original ENUM values
            DB::statement("ALTER TABLE meter_readings MODIFY COLUMN reading_type ENUM('manual', 'automatic') NOT NULL DEFAULT 'manual'");
            
            // Optional: Revert values back
            DB::table('meter_readings')->where('reading_type', 'actual')->update(['reading_type' => 'manual']);
            DB::table('meter_readings')->where('reading_type', 'estimated')->update(['reading_type' => 'automatic']);
        } else {
            // For other databases, change is minimal
            Schema::table('meter_readings', function (Blueprint $table) {
                $table->string('reading_type', 20)->default('manual')->change();
            });
        }

        if (Schema::hasColumn('billing_details', 'meter_id')) {
            Schema::table('billing_details', function (Blueprint $table) {
                $table->dropForeign(['meter_id']);
                $table->dropColumn('meter_id');
            });
        }
    }
};
