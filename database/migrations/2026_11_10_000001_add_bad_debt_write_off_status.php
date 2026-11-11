<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE billings MODIFY COLUMN status ENUM('pending', 'paid', 'overdue', 'partially_paid', 'voided', 'written_off') DEFAULT 'pending'");
        DB::statement("ALTER TABLE credit_notes MODIFY COLUMN type ENUM('previous_resident_debt', 'billing_error', 'goodwill', 'other', 'bad_debt_writeoff') DEFAULT 'previous_resident_debt'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE billings SET status = 'paid' WHERE status = 'written_off'");
        DB::statement("UPDATE credit_notes SET type = 'other' WHERE type = 'bad_debt_writeoff'");
        DB::statement("ALTER TABLE billings MODIFY COLUMN status ENUM('pending', 'paid', 'overdue', 'partially_paid', 'voided') DEFAULT 'pending'");
        DB::statement("ALTER TABLE credit_notes MODIFY COLUMN type ENUM('previous_resident_debt', 'billing_error', 'goodwill', 'other') DEFAULT 'previous_resident_debt'");
    }
};
