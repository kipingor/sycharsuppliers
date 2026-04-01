<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Step 1: Add new columns ────────────────────────────────────────
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('direction', 10)->default('outbound')->after('id');

            // Sender info (populated for inbound; mirrors from-address for outbound)
            $table->string('from_email')->nullable()->after('direction');
            $table->string('from_name')->nullable()->after('from_email');

            // Link to an account (nullable — not all emails map to an account)
            $table->foreignId('account_id')
                  ->nullable()
                  ->after('from_name')
                  ->constrained('accounts')
                  ->nullOnDelete();

            // SMTP/Mailgun identifiers
            $table->string('message_id')->nullable()->after('account_id')
                  ->comment('SMTP Message-Id header — used for threading');
            $table->string('mailgun_id')->nullable()->after('message_id')
                  ->comment('Mailgun event/storage ID — used for deduplication');
            $table->string('in_reply_to')->nullable()->after('mailgun_id')
                  ->comment('Message-Id of the parent email');

            // Attachments metadata: [{name, mime, size, path}]
            $table->json('attachments')->nullable()->after('in_reply_to');

            // Full webhook payload saved for debugging/replay (bodies excluded to save space)
            $table->json('raw_payload')->nullable()->after('attachments');

            // Tracking timestamps
            $table->timestamp('read_at')->nullable()->after('sent_at');
            $table->timestamp('delivered_at')->nullable()->after('read_at');
            $table->timestamp('opened_at')->nullable()->after('delivered_at');
            $table->timestamp('bounced_at')->nullable()->after('opened_at');
        });

        // ── Step 2: Widen status ENUM → VARCHAR ───────────────────────────
        // Safe swap: add new column, copy values, drop old, rename.
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('status_new', 30)->default('queued')->after('body');
        });

        DB::statement('UPDATE email_logs SET status_new = status');

        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->renameColumn('status_new', 'status');
        });

        // ── Step 3: Indexes ────────────────────────────────────────────────
        Schema::table('email_logs', function (Blueprint $table) {
            $table->index('direction');
            $table->index('account_id');
            $table->index('message_id');
            $table->index('mailgun_id');
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex(['direction']);
            $table->dropIndex(['account_id']);
            $table->dropIndex(['message_id']);
            $table->dropIndex(['mailgun_id']);
            $table->dropIndex(['read_at']);

            $table->dropForeign(['account_id']);
            $table->dropColumn([
                'direction', 'from_email', 'from_name', 'account_id',
                'message_id', 'mailgun_id', 'in_reply_to',
                'attachments', 'raw_payload',
                'read_at', 'delivered_at', 'opened_at', 'bounced_at',
            ]);
        });

        // Restore narrow status column
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('status_restore', 10)->default('failed')->after('body');
        });
        DB::statement("UPDATE email_logs SET status_restore = CASE WHEN status IN ('sent','failed') THEN status ELSE 'failed' END");
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('email_logs', function (Blueprint $table) {
            $table->renameColumn('status_restore', 'status');
        });
    }
};
