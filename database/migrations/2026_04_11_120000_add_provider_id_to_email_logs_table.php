<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('provider_id')->nullable()->after('message_id')
                ->comment('Provider-specific email identifier used for webhook matching');
        });

        DB::statement('UPDATE email_logs SET provider_id = mailgun_id WHERE provider_id IS NULL AND mailgun_id IS NOT NULL');

        Schema::table('email_logs', function (Blueprint $table) {
            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex(['provider_id']);
            $table->dropColumn('provider_id');
        });
    }
};
