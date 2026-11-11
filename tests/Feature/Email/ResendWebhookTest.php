<?php

namespace Tests\Feature\Email;

use App\Models\Account;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Resend\Contracts\Client as ResendClient;
use Tests\TestCase;

class ResendWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'resend.webhook.secret' => 'whsec_dGVzdF9zZWNyZXRfZm9yX3dlYmhvb2tz',
            'resend.webhook.tolerance' => 300,
        ]);
    }

    public function test_email_received_webhook_creates_an_inbound_email_log(): void
    {
        $account = Account::factory()->create([
            'email' => 'customer@example.com',
        ]);

        $details = (object) [
            'from' => 'Customer Example <customer@example.com>',
            'to' => ['billing@sycharsuppliers.com'],
            'subject' => 'Water bill question',
            'html' => '<p>Hello team</p>',
            'text' => 'Hello team',
            'message_id' => '<inbound-123@example.test>',
            'headers' => [
                'In-Reply-To' => '<parent-456@example.test>',
            ],
            'attachments' => [],
        ];

        $this->app->instance(ResendClient::class, $this->fakeResendClient($details));

        $payload = [
            'type' => 'email.received',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'email_id' => 'rx_123',
                'from' => 'Customer Example <customer@example.com>',
                'to' => ['billing@sycharsuppliers.com'],
                'subject' => 'Water bill question',
            ],
        ];

        $response = $this->withHeaders($this->signedHeaders($payload))
            ->postJson('/api/webhooks/resend', $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('email_logs', [
            'direction' => 'inbound',
            'provider_id' => 'rx_123',
            'from_email' => 'customer@example.com',
            'account_id' => $account->id,
            'recipient_email' => 'billing@sycharsuppliers.com',
            'subject' => 'Water bill question',
            'message_id' => 'inbound-123@example.test',
            'in_reply_to' => 'parent-456@example.test',
            'status' => EmailLog::STATUS_RECEIVED,
        ]);
    }

    public function test_received_message_routes_to_a_known_shared_address(): void
    {
        config(['mail.shared_addresses' => ['sales@sycharsuppliers.com']]);
        User::factory()->create(['company_email' => 'jane.doe@sycharsuppliers.com']);
        $details = (object) [
            'from' => 'Customer <customer@example.com>',
            'to' => ['jane.doe@sycharsuppliers.com', 'sales@sycharsuppliers.com'],
            'subject' => 'Shared inbox request',
            'text' => 'Please help.',
            'message_id' => '<shared-123@example.test>',
            'headers' => [],
            'attachments' => [],
        ];
        $this->app->instance(ResendClient::class, $this->fakeResendClient($details));

        $payload = [
            'type' => 'email.received',
            'created_at' => now()->toIso8601String(),
            'data' => ['email_id' => 'rx_shared', 'to' => $details->to],
        ];

        $this->withHeaders($this->signedHeaders($payload))
            ->postJson('/api/webhooks/resend', $payload)
            ->assertOk();

        $this->assertDatabaseHas('email_logs', [
            'provider_id' => 'rx_shared',
            'recipient_email' => 'sales@sycharsuppliers.com',
        ]);
    }

    public function test_delivery_webhook_updates_the_matching_outbound_email_log(): void
    {
        $log = EmailLog::create([
            'direction' => 'outbound',
            'from_email' => 'billing@sycharsuppliers.com',
            'from_name' => 'Sychar Suppliers',
            'recipient_email' => 'customer@example.com',
            'subject' => 'Statement ready',
            'body' => '<p>Attached</p>',
            'status' => EmailLog::STATUS_SENT,
            'provider_id' => 're_456',
            'sent_at' => now(),
        ]);

        $payload = [
            'type' => 'email.delivered',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'email_id' => 're_456',
                'to' => ['customer@example.com'],
                'subject' => 'Statement ready',
            ],
        ];

        $response = $this->withHeaders($this->signedHeaders($payload))
            ->postJson('/api/webhooks/resend', $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $log->refresh();

        $this->assertSame(EmailLog::STATUS_DELIVERED, $log->status);
        $this->assertNotNull($log->delivered_at);
    }

    public function test_delayed_and_out_of_order_events_do_not_regress_delivery_status(): void
    {
        $log = EmailLog::create([
            'direction' => 'outbound',
            'from_email' => 'billing@sycharsuppliers.com',
            'recipient_email' => 'customer@example.com',
            'subject' => 'Statement ready',
            'body' => '<p>Attached</p>',
            'status' => EmailLog::STATUS_SENT,
            'provider_id' => 're_789',
            'sent_at' => now()->subMinutes(3),
        ]);

        $this->postWebhookEvent('email.delivered', 're_789', now()->subMinute());
        $this->postWebhookEvent('email.failed', 're_789', now()->subSeconds(30));
        $this->postWebhookEvent('email.delivery_delayed', 're_789', now());
        $this->postWebhookEvent('email.sent', 're_789', now()->subMinutes(2));

        $log->refresh();

        $this->assertSame(EmailLog::STATUS_DELIVERED, $log->status);
        $this->assertNotNull($log->delivered_at);
    }

    public function test_provider_id_prevents_events_from_matching_a_different_email_with_same_subject(): void
    {
        $log = EmailLog::create([
            'direction' => 'outbound',
            'from_email' => 'billing@sycharsuppliers.com',
            'recipient_email' => 'customer@example.com',
            'subject' => 'Statement ready',
            'body' => '<p>Attached</p>',
            'status' => EmailLog::STATUS_SENT,
            'provider_id' => 're_existing',
            'sent_at' => now(),
        ]);

        $this->postWebhookEvent('email.delivered', 're_unknown', now());

        $this->assertSame(EmailLog::STATUS_SENT, $log->fresh()->status);
        $this->assertNull($log->fresh()->delivered_at);
    }

    private function postWebhookEvent(string $type, string $providerId, \Illuminate\Support\Carbon $createdAt): void
    {
        $payload = [
            'type' => $type,
            'created_at' => $createdAt->toIso8601String(),
            'data' => [
                'email_id' => $providerId,
                'to' => ['customer@example.com'],
                'subject' => 'Statement ready',
            ],
        ];

        $this->withHeaders($this->signedHeaders($payload))
            ->postJson('/api/webhooks/resend', $payload)
            ->assertOk();
    }

    private function fakeResendClient(object $details): ResendClient
    {
        return new class($details) implements ResendClient
        {
            public object $emails;

            public function __construct(object $details)
            {
                $this->emails = new class($details)
                {
                    public object $receiving;

                    public function __construct(object $details)
                    {
                        $this->receiving = new class($details)
                        {
                            public function __construct(private object $details) {}

                            public function get(string $id): object
                            {
                                return $this->details;
                            }
                        };
                    }
                };
            }
        };
    }

    private function signedHeaders(array $payload): array
    {
        $secret = 'whsec_dGVzdF9zZWNyZXRfZm9yX3dlYmhvb2tz';
        $messageId = 'msg_'.str()->random(12);
        $timestamp = time();
        $signature = $this->signWebhook($secret, $messageId, $timestamp, json_encode($payload, JSON_UNESCAPED_SLASHES));

        return [
            'svix-id' => $messageId,
            'svix-timestamp' => (string) $timestamp,
            'svix-signature' => $signature,
        ];
    }

    private function signWebhook(string $secret, string $messageId, int $timestamp, string $payload): string
    {
        $decodedSecret = base64_decode(substr($secret, strlen('whsec_')));
        $signedContent = "{$messageId}.{$timestamp}.{$payload}";
        $hash = hash_hmac('sha256', $signedContent, $decodedSecret);
        $signature = base64_encode(pack('H*', $hash));

        return "v1,{$signature}";
    }
}
