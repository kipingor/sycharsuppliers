<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_email')->nullable()->unique()->after('email');
        });

        $domain = config('mail.company_domain', 'sycharsuppliers.com');
        DB::table('users')->whereNull('company_email')->orderBy('id')->chunkById(100, function ($users) use ($domain) {
            foreach ($users as $user) {
                $base = (string) Str::of(Str::ascii($user->name))
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '.')
                    ->trim('.');
                $base = $base ?: 'user';
                $candidate = "{$base}@{$domain}";
                $suffix = 2;

                while (DB::table('users')->where('company_email', $candidate)->exists()) {
                    $candidate = "{$base}.{$suffix}@{$domain}";
                    $suffix++;
                }

                DB::table('users')->where('id', $user->id)->update(['company_email' => $candidate]);
            }
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreignId('sender_user_id')->nullable()->after('billing_id')->constrained('users')->nullOnDelete();
            $table->foreignId('thread_id')->nullable()->after('sender_user_id')->constrained('email_logs')->nullOnDelete();
            $table->foreignId('claimed_by')->nullable()->after('thread_id')->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('claimed_by');
            $table->index(['recipient_email', 'direction']);
            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex(['recipient_email', 'direction']);
            $table->dropIndex(['thread_id', 'created_at']);
            $table->dropConstrainedForeignId('sender_user_id');
            $table->dropConstrainedForeignId('thread_id');
            $table->dropConstrainedForeignId('claimed_by');
            $table->dropColumn('claimed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['company_email']);
            $table->dropColumn('company_email');
        });
    }
};
