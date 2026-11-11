<?php

namespace Tests\Feature\Email;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SharedInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.shared_addresses' => ['sales@sycharsuppliers.com']]);
    }

    public function test_claimed_shared_conversation_can_be_replied_to_from_its_shared_address(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'company_email' => 'jane.doe@sycharsuppliers.com',
            'email_verified_at' => now(),
        ]);
        $inbound = $this->createSharedInbound();

        $this->actingAs($user)
            ->post(route('emails.claim', $inbound))
            ->assertSessionHas('success');

        $this->post(route('emails.send'), [
            'to' => 'customer@example.com',
            'from_email' => 'sales@sycharsuppliers.com',
            'subject' => 'Re: Sales question',
            'body' => '<p>We will help.</p>',
            'in_reply_to' => $inbound->message_id,
        ])->assertRedirect();

        $this->assertDatabaseHas('email_logs', [
            'direction' => 'outbound',
            'from_email' => 'sales@sycharsuppliers.com',
            'recipient_email' => 'customer@example.com',
            'sender_user_id' => $user->id,
            'thread_id' => $inbound->id,
            'in_reply_to' => $inbound->message_id,
        ]);
        Mail::assertSent(\App\Mail\GenericEmail::class, fn ($mail) =>
            $mail->envelope()->from?->address === 'sales@sycharsuppliers.com'
        );
    }

    public function test_another_user_cannot_reply_to_a_conversation_claimed_by_a_colleague(): void
    {
        Mail::fake();
        $claimant = User::factory()->create([
            'company_email' => 'jane.doe@sycharsuppliers.com',
            'email_verified_at' => now(),
        ]);
        $otherUser = User::factory()->create([
            'company_email' => 'john.doe@sycharsuppliers.com',
            'email_verified_at' => now(),
        ]);
        $inbound = $this->createSharedInbound();

        $this->actingAs($claimant)
            ->post(route('emails.claim', $inbound))
            ->assertSessionHas('success');

        $this->actingAs($otherUser)
            ->post(route('emails.send'), [
                'to' => 'customer@example.com',
                'from_email' => 'sales@sycharsuppliers.com',
                'subject' => 'Re: Sales question',
                'body' => '<p>Duplicate response.</p>',
                'in_reply_to' => $inbound->message_id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('email_logs', [
            'direction' => 'outbound',
            'body' => '<p>Duplicate response.</p>',
        ]);
    }

    public function test_reply_to_a_shared_outbound_message_uses_the_shared_conversation_claim(): void
    {
        $user = User::factory()->create([
            'company_email' => 'jane.doe@sycharsuppliers.com',
            'email_verified_at' => now(),
        ]);
        $outbound = EmailLog::create([
            'direction' => 'outbound',
            'from_email' => 'sales@sycharsuppliers.com',
            'recipient_email' => 'customer@example.com',
            'subject' => 'Sales introduction',
            'body' => '<p>Hello.</p>',
            'status' => EmailLog::STATUS_SENT,
            'message_id' => 'sales-introduction@example.test',
        ]);
        $outbound->update(['thread_id' => $outbound->id]);
        $inbound = EmailLog::create([
            'direction' => 'inbound',
            'from_email' => 'customer@example.com',
            'recipient_email' => 'sales@sycharsuppliers.com',
            'subject' => 'Re: Sales introduction',
            'body' => '<p>Thank you.</p>',
            'status' => EmailLog::STATUS_RECEIVED,
            'message_id' => 'customer-reply@example.test',
            'in_reply_to' => $outbound->message_id,
            'thread_id' => $outbound->id,
        ]);

        $this->actingAs($user)
            ->post(route('emails.claim', $inbound))
            ->assertSessionHas('success');

        $this->assertSame($user->id, $outbound->fresh()->claimed_by);
        $this->assertSame($outbound->id, $inbound->conversationRoot()->id);
    }

    public function test_personal_inbox_messages_are_not_visible_to_other_users(): void
    {
        $user = User::factory()->create([
            'company_email' => 'jane.doe@sycharsuppliers.com',
            'email_verified_at' => now(),
        ]);
        $privateEmail = EmailLog::create([
            'direction' => 'inbound',
            'from_email' => 'customer@example.com',
            'recipient_email' => 'john.doe@sycharsuppliers.com',
            'subject' => 'Private mailbox message',
            'body' => '<p>Private.</p>',
            'status' => EmailLog::STATUS_RECEIVED,
        ]);

        $this->actingAs($user)
            ->get(route('emails.show', $privateEmail))
            ->assertForbidden();
    }

    private function createSharedInbound(): EmailLog
    {
        $email = EmailLog::create([
            'direction' => 'inbound',
            'from_email' => 'customer@example.com',
            'recipient_email' => 'sales@sycharsuppliers.com',
            'subject' => 'Sales question',
            'body' => '<p>Can you help?</p>',
            'status' => EmailLog::STATUS_RECEIVED,
            'message_id' => 'sales-question@example.test',
        ]);
        $email->update(['thread_id' => $email->id]);

        return $email->fresh();
    }
}
