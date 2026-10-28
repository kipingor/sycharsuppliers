<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Billing;
use App\Models\BillingDetail;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterReplacementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacing_a_faulty_meter_keeps_its_history_and_records_both_readings(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');
        $account = Account::factory()->create();
        $resident = Resident::factory()->create([
            'account_number' => $account->account_number,
        ]);
        $meter = $this->createMeter($account, $resident, [
            'meter_number' => 'MTR-OLD-001',
            'status' => 'faulty',
        ]);
        $previousReading = MeterReading::create([
            'meter_id' => $meter->id,
            'reading_date' => today()->subDay()->toDateString(),
            'reading_value' => 100,
            'reading_type' => 'actual',
            'consumption' => 0,
        ]);
        $billing = Billing::factory()->create([
            'account_id' => $account->id,
            'meter_id' => $meter->id,
        ]);
        $billingDetail = BillingDetail::factory()->create([
            'billing_id' => $billing->id,
            'meter_id' => $meter->id,
            'previous_reading_value' => 100,
            'current_reading_value' => 110,
            'units_used' => 10,
        ]);

        $response = $this->actingAs($user)->post(route('meters.replace', $meter), [
            'meter_number' => 'MTR-NEW-001',
            'meter_name' => 'Replacement meter',
            'location' => 'Utility room',
            'final_reading' => '120.25',
            'initial_reading' => '2.50',
            'notes' => 'Replaced after fault inspection',
        ]);

        $replacement = Meter::where('meter_number', 'MTR-NEW-001')->firstOrFail();
        $meter->refresh();

        $response->assertRedirect(route('meters.show', $replacement))
            ->assertSessionHas('success');

        $this->assertSame('replaced', $meter->status);
        $this->assertSame($replacement->id, $meter->replaced_by_meter_id);
        $this->assertSame('active', $replacement->status);
        $this->assertSame($account->id, $replacement->account_id);
        $this->assertSame($resident->id, $replacement->resident_id);

        $this->assertDatabaseHas('meter_readings', [
            'id' => $previousReading->id,
            'meter_id' => $meter->id,
            'reading_value' => 100,
        ]);
        $this->assertDatabaseHas('meter_readings', [
            'meter_id' => $meter->id,
            'reading_value' => 120.25,
            'consumption' => 20.25,
        ]);
        $this->assertDatabaseHas('meter_readings', [
            'meter_id' => $replacement->id,
            'reading_value' => 2.5,
            'consumption' => 0,
        ]);
        $this->assertDatabaseHas('billing_details', [
            'id' => $billingDetail->id,
            'meter_id' => $meter->id,
        ]);

        $this->actingAs($user)
            ->from(route('meters.edit', $meter))
            ->put(route('meters.update', $meter), ['status' => 'active'])
            ->assertRedirect(route('meters.edit', $meter))
            ->assertSessionHasErrors('status');

        $this->assertSame('replaced', $meter->fresh()->status);
    }

    public function test_updating_status_does_not_treat_the_unchanged_account_as_a_change(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');
        $account = Account::factory()->create();
        $resident = Resident::factory()->create([
            'account_number' => $account->account_number,
        ]);
        $meter = $this->createMeter($account, $resident);

        MeterReading::create([
            'meter_id' => $meter->id,
            'reading_date' => today()->subDay()->toDateString(),
            'reading_value' => 100,
            'reading_type' => 'actual',
            'consumption' => 0,
        ]);

        $this->actingAs($user)
            ->from(route('meters.edit', $meter))
            ->put(route('meters.update', $meter), [
                'account_id' => (string) $account->id,
                'meter_number' => $meter->meter_number,
                'meter_name' => $meter->meter_name,
                'meter_type' => $meter->meter_type,
                'status' => 'faulty',
                'location' => $meter->location,
            ])
            ->assertRedirect(route('meters.show', $meter))
            ->assertSessionHas('success', 'Meter updated successfully');

        $this->assertSame('faulty', $meter->fresh()->status);
    }

    public function test_replacement_is_rejected_when_final_reading_is_below_the_latest_reading(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');
        $account = Account::factory()->create();
        $resident = Resident::factory()->create([
            'account_number' => $account->account_number,
        ]);
        $meter = $this->createMeter($account, $resident, [
            'meter_number' => 'MTR-OLD-002',
            'status' => 'faulty',
        ]);
        MeterReading::create([
            'meter_id' => $meter->id,
            'reading_date' => today()->subDay()->toDateString(),
            'reading_value' => 100,
            'reading_type' => 'actual',
            'consumption' => 0,
        ]);

        $this->actingAs($user)
            ->from(route('meters.replace.create', $meter))
            ->post(route('meters.replace', $meter), [
                'meter_number' => 'MTR-NEW-002',
                'meter_name' => 'Replacement meter',
                'location' => 'Utility room',
                'final_reading' => '99.99',
                'initial_reading' => '0',
            ])
            ->assertRedirect(route('meters.replace.create', $meter))
            ->assertSessionHasErrors('final_reading');

        $this->assertDatabaseMissing('meters', [
            'meter_number' => 'MTR-NEW-002',
        ]);
        $this->assertSame('faulty', $meter->fresh()->status);
    }

    public function test_replacing_a_bulk_meter_reassigns_its_submeters(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');
        $account = Account::factory()->create();
        $resident = Resident::factory()->create([
            'account_number' => $account->account_number,
        ]);
        $bulkMeter = $this->createMeter($account, $resident, [
            'meter_number' => 'MTR-BULK-OLD',
            'meter_type' => 'bulk',
            'status' => 'faulty',
        ]);
        $subMeter = $this->createMeter($account, $resident, [
            'meter_number' => 'MTR-SUB-001',
            'parent_meter_id' => $bulkMeter->id,
            'allocation_percentage' => 40,
        ]);

        $this->actingAs($user)->post(route('meters.replace', $bulkMeter), [
            'meter_number' => 'MTR-BULK-NEW',
            'meter_name' => 'Replacement bulk meter',
            'location' => 'Utility room',
            'final_reading' => '0',
            'initial_reading' => '0',
        ])->assertSessionHas('success');

        $replacement = Meter::where('meter_number', 'MTR-BULK-NEW')->firstOrFail();
        $this->assertSame($replacement->id, $subMeter->fresh()->parent_meter_id);
        $this->assertSame('40.00', $subMeter->fresh()->allocation_percentage);
    }

    private function createMeter(Account $account, Resident $resident, array $attributes = []): Meter
    {
        return Meter::create(array_merge([
            'account_id' => $account->id,
            'resident_id' => $resident->id,
            'meter_number' => 'MTR-TEST',
            'meter_name' => 'Test meter',
            'location' => 'Utility room',
            'type' => 'water',
            'meter_type' => 'individual',
            'status' => 'active',
        ], $attributes));
    }
}
