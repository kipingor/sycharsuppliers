<?php

namespace Tests\Feature\Email;

use App\Models\EmailLog;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_outbound_emails_as_sent(): void
    {
        Mail::fake();

        $log = app(EmailService::class)->send(
            'customer@example.com',
            'Test subject',
            '<p>Hello</p>'
        );

        Mail::assertSent(\App\Mail\GenericEmail::class);

        $this->assertSame(EmailLog::STATUS_SENT, $log->fresh()->status);
        $this->assertSame('customer@example.com', $log->fresh()->recipient_email);
        $this->assertSame('Test subject', $log->fresh()->subject);
        $this->assertNotNull($log->fresh()->sent_at);
    }
}
