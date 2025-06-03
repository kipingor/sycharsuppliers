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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->nullable()->constrained()->onDelete('set null');
            $table->string('report_type'); // Billing, Meter Reading, Payments, etc.
            $table->timestamp('generated_at')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['pending', 'available', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
