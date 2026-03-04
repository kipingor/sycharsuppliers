<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Enhance expenses table ────────────────────────────────────────────
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'receipt_path')) {
                $table->string('receipt_path')->nullable()->after('receipt_number')
                    ->comment('Stored path to uploaded receipt file');
            }
            if (!Schema::hasColumn('expenses', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_by')
                    ->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('expenses', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (!Schema::hasColumn('expenses', 'rejection_reason')) {
                $table->string('rejection_reason')->nullable()->after('rejected_at');
            }
            if (!Schema::hasColumn('expenses', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // ── Create expense_budgets table ──────────────────────────────────────
        if (!Schema::hasTable('expense_budgets')) {
            Schema::create('expense_budgets', function (Blueprint $table) {
                $table->id();
                $table->string('category');
                $table->decimal('monthly_limit', 12, 2);
                $table->year('year');
                $table->tinyInteger('month'); // 1–12; 0 = applies to all months of the year
                $table->boolean('active')->default(true);
                $table->string('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->unique(['category', 'year', 'month'], 'budgets_category_period_unique');
                $table->index(['year', 'month']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropSoftDeletes();
            foreach (['receipt_path', 'rejected_by', 'rejected_at', 'rejection_reason'] as $col) {
                if (Schema::hasColumn('expenses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('expense_budgets');
    }
};
