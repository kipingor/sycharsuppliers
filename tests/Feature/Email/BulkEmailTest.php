<?php

namespace Tests\Feature\Email;

use App\Models\Account;
use App\Models\Billing;
use App\Models\EmailLog;
use App\Models\Meter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BulkEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_personalized_reminders_only_to_accounts_with_overdue_balances(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->assignRole('admin');

        $overdueMeter = Meter::factory()->create();
        $overdueAccount = $overdueMeter->account;
        $overdueAccount->update(['name' => 'Overdue Customer']);
        Billing::factory()->create([
            'meter_id' => $overdueMeter->id,
            'account_id' => $overdueAccount->id,
            'status' => 'pending',
            'due_date' => today()->subDay(),
            'amount_due' => 1250,
        ]);

        $currentMeter = Meter::factory()->create();
        Billing::factory()->create([
            'meter_id' => $currentMeter->id,
            'account_id' => $currentMeter->account_id,
            'status' => 'pending',
            'due_date' => today()->addDay(),
            'amount_due' => 500,
        ]);

        $this->actingAs($user)->post(route('emails.send-bulk'), [
            'campaign' => 'overdue_reminder',
            'subject' => 'Overdue balance',
            'body' => '<p>{{name}} {{account_number}} {{overdue_amount}}</p>',
        ])->assertRedirect(route('emails.index'));

        $this->assertSame(1, EmailLog::outbound()->count());
        $log = EmailLog::outbound()->firstOrFail();
        $this->assertSame($overdueAccount->email, $log->recipient_email);
        $this->assertStringContainsString('Overdue Customer', $log->body);
        $this->assertStringContainsString($overdueAccount->account_number, $log->body);
        $this->assertStringContainsString('1,250.00 KES', $log->body);
        $this->assertSame(
            "account_statement_{$overdueAccount->account_number}.pdf",
            $log->attachments[0]['name']
        );
        Mail::assertSent(\App\Mail\GenericEmail::class, fn ($mail) => count($mail->attachments()) === 1);
    }

    public function test_announcements_are_sent_only_to_active_accounts(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->assignRole('admin');
        $activeAccount = Account::factory()->create(['status' => 'active']);
        Account::factory()->create(['status' => 'inactive']);

        $this->actingAs($user)->post(route('emails.send-bulk'), [
            'campaign' => 'announcement',
            'subject' => 'Water supply notice',
            'body' => '<p>Scheduled maintenance notice.</p>',
        ])->assertRedirect(route('emails.index'));

        $this->assertSame(1, EmailLog::outbound()->count());
        $this->assertSame($activeAccount->email, EmailLog::outbound()->firstOrFail()->recipient_email);
    }

    public function test_it_sends_an_individual_overdue_reminder_with_the_account_statement(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->assignRole('admin');

        $meter = Meter::factory()->create();
        $account = $meter->account;
        Billing::factory()->create([
            'meter_id' => $meter->id,
            'account_id' => $account->id,
            'status' => 'overdue',
            'due_date' => today()->subDay(),
            'amount_due' => 725,
        ]);

        $this->actingAs($user)->post(route('emails.send-overdue'), [
            'account_id' => $account->id,
            'subject' => 'Your overdue balance',
            'body' => '<p>{{name}} {{account_number}} {{overdue_amount}}</p>',
        ])->assertRedirect();

        $log = EmailLog::outbound()->firstOrFail();
        $this->assertSame($account->email, $log->recipient_email);
        $this->assertSame($account->id, $log->account_id);
        $this->assertStringContainsString($account->name, $log->body);
        $this->assertStringContainsString($account->account_number, $log->body);
        $this->assertStringContainsString('725.00 KES', $log->body);
        $this->assertSame(
            "account_statement_{$account->account_number}.pdf",
            $log->attachments[0]['name']
        );
        Mail::assertSent(\App\Mail\GenericEmail::class, fn ($mail) => count($mail->attachments()) === 1);
    }

    public function test_it_rejects_an_individual_overdue_reminder_for_an_account_without_overdue_balance(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->assignRole('admin');
        $account = Account::factory()->create();

        $this->actingAs($user)->from(route('accounts.show', $account))->post(route('emails.send-overdue'), [
            'account_id' => $account->id,
            'subject' => 'Your overdue balance',
            'body' => '<p>Please see your statement.</p>',
        ])->assertRedirect(route('accounts.show', $account))
            ->assertSessionHas('error');

        $this->assertSame(0, EmailLog::outbound()->count());
        Mail::assertNothingSent();
    }
}
