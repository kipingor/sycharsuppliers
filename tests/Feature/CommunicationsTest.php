<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\PriorityMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunicationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('system_settings.groups');
        Role::findOrCreate('field_officer', 'web');
    }

    public function test_priority_message_is_private_and_requires_acknowledgement(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($sender)->postJson(route('communications.priority.store'), [
            'recipient_id' => $recipient->id,
            'message' => 'Urgent meter outage',
            'priority_level' => 'critical',
        ]);

        $response->assertCreated()->assertJson(['sent' => 1]);
        $priority = PriorityMessage::firstOrFail();
        $this->assertSame($recipient->id, $priority->recipient_id);
        $this->assertNull($priority->acknowledged_at);

        $this->actingAs($recipient)->getJson(route('communications.summary'))
            ->assertOk()
            ->assertJsonPath('priorities.0.message', 'Urgent meter outage')
            ->assertJsonPath('notifications.count', 1);
        $this->actingAs($otherUser)->getJson(route('communications.summary'))
            ->assertOk()->assertJsonCount(0, 'priorities');
        $this->actingAs($otherUser)->postJson(route('communications.priority.acknowledge', $priority))->assertForbidden();
        $this->actingAs($recipient)->postJson(route('communications.priority.acknowledge', $priority))
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertNotNull($priority->fresh()->acknowledged_at);
        $notificationId = $recipient->notifications()->firstOrFail()->id;
        $this->actingAs($recipient)->postJson(route('communications.notifications.read', $notificationId))
            ->assertOk()->assertJson(['ok' => true]);
    }

    public function test_priority_message_can_be_targeted_to_a_role(): void
    {
        $sender = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $first->assignRole('field_officer');
        $second->assignRole('field_officer');

        $this->actingAs($sender)->postJson(route('communications.priority.store'), [
            'recipient_role' => 'field_officer',
            'message' => 'Shift handover',
            'priority_level' => 'high',
        ])->assertCreated()->assertJson(['sent' => 2]);

        $this->assertDatabaseCount('priority_messages', 2);
    }

    public function test_users_can_chat_only_in_their_conversations(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();

        $created = $this->actingAs($sender)->postJson(route('communications.conversations.store'), [
            'user_id' => $recipient->id,
        ])->assertCreated()->assertJsonStructure(['id'])->json('id');
        $conversation = Conversation::findOrFail($created);

        $this->actingAs($sender)->postJson(route('communications.conversations.messages.store', $conversation), [
            'body' => 'Can you check the pump?',
        ])->assertCreated();
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => 'Can you check the pump?',
        ]);

        $this->actingAs($recipient)->getJson(route('communications.conversations.messages', $conversation))
            ->assertOk()->assertJsonPath('messages.0.body', 'Can you check the pump?');
        $this->assertNotNull(ChatMessage::firstOrFail()->fresh()->read_at);
        $this->assertSame(0, $recipient->fresh()->unreadNotifications()->count());

        $this->actingAs($outsider)->getJson(route('communications.conversations.messages', $conversation))->assertNotFound();
        $this->actingAs($outsider)->postJson(route('communications.conversations.messages.store', $conversation), [
            'body' => 'Unauthorized',
        ])->assertNotFound();
    }

    public function test_announcement_publishing_is_admin_only(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();
        $payload = ['title' => 'Maintenance', 'body' => 'Planned downtime tonight', 'priority_level' => 'high'];

        $this->actingAs($user)->postJson(route('communications.announcements.store'), $payload)->assertForbidden();
        $this->actingAs($admin)->postJson(route('communications.announcements.store'), $payload)->assertCreated();
        $this->actingAs($user)->getJson(route('communications.summary'))
            ->assertJsonPath('announcements.0.title', 'Maintenance');
    }
}
