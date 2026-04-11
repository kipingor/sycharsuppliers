<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            if (!Schema::hasColumn('billings', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('paid_at');
            }

            if (!Schema::hasColumn('billings', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('void_reason');
            }

            if (!Schema::hasColumn('billings', 'replaced_billing_id')) {
                $table->foreignId('replaced_billing_id')->nullable()->after('voided_at')
                    ->constrained('billings')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            if (Schema::hasColumn('billings', 'replaced_billing_id')) {
                $table->dropForeign(['replaced_billing_id']);
                $table->dropColumn('replaced_billing_id');
            }

            if (Schema::hasColumn('billings', 'voided_at')) {
                $table->dropColumn('voided_at');
            }

            if (Schema::hasColumn('billings', 'void_reason')) {
                $table->dropColumn('void_reason');
            }
        });
    }
};
