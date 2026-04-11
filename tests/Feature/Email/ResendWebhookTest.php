<?php

namespace Tests\Feature\Email;

use App\Models\Account;
use App\Models\EmailLog;
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
