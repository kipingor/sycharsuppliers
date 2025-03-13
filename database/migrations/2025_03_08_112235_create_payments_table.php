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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();            
            $table->foreignId('billing_id')->constrained()->onDelete('cascade');
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['M-Pesa', 'Bank Transfer', 'Cash']);
            $table->string('transaction_id')->unique();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
