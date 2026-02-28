<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();

            // The bill this credit note is applied to
            $table->foreignId('billing_id')
                  ->constrained('billings')
                  ->cascadeOnDelete();

            // Optional: track who the credit is being moved away from (previous resident's account)
            $table->foreignId('previous_account_id')
                  ->nullable()
                  ->constrained('accounts')
                  ->nullOnDelete();

            $table->string('reference')->unique(); // e.g. CN-2024-0001

            $table->enum('type', [
                'previous_resident_debt',   // debt from a prior resident — main use case
                'billing_error',            // admin correction
                'goodwill',                 // goodwill adjustment
                'other',
            ])->default('previous_resident_debt');

            $table->decimal('amount', 12, 2);   // amount being credited (positive)
            $table->text('reason');             // required explanation

            $table->enum('status', ['applied', 'voided'])->default('applied');
            $table->text('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignId('voided_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};