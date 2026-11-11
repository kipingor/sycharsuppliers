<?php

namespace Tests\Feature\Email;

use App\Models\EmailLog;
use App\Models\Billing;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

    public function test_custom_bill_email_is_tracked_against_its_bill(): void
    {
        Mail::fake();
        $billing = Billing::factory()->create();

        $log = app(EmailService::class)->sendMailable(
            $billing->account->email,
            'Bill statement',
            '<p>Bill attached</p>',
            new \App\Mail\BillingStatement('pdf-content', $billing),
            [
                'account_id' => $billing->account_id,
                'billing_id' => $billing->id,
                'attachments' => [[
                    'name' => "bill_statement_{$billing->id}.pdf",
                    'mime' => 'application/pdf',
                    'size' => 11,
                ]],
            ],
        );

        $this->assertSame(EmailLog::STATUS_SENT, $log->fresh()->status);
        $this->assertSame($billing->id, $log->fresh()->billing_id);
        $this->assertSame("bill_statement_{$billing->id}.pdf", $log->fresh()->attachments[0]['name']);
        Mail::assertSent(\App\Mail\BillingStatement::class);
    }

    public function test_it_reads_message_id_from_laravels_sent_message_wrapper(): void
    {
        Config::set('mail.default', 'array');

        $log = app(EmailService::class)->send(
            'customer@example.com',
            'Message ID test',
            '<p>Hello</p>',
        );

        $this->assertSame(EmailLog::STATUS_SENT, $log->fresh()->status);
        $this->assertNotEmpty($log->fresh()->message_id);
    }
}
