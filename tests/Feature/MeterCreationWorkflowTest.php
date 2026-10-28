<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterCreationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_meter_creation_persists_the_meter_and_its_initial_reading(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');
        $account = Account::factory()->create();
        $resident = Resident::factory()->create([
            'account_number' => $account->account_number,
            'status' => true,
        ]);

        $response = $this->actingAs($user)->post(route('meters.store'), [
            'account_id' => $account->id,
            'resident_id' => $resident->id,
            'meter_number' => 'MTR-10001',
            'meter_name' => 'Main meter',
            'location' => 'Utility room',
            'type' => 'water',
            'meter_type' => 'individual',
            'status' => 'active',
            'installed_at' => today()->toDateString(),
            'initial_reading' => '125.50',
            'notes' => 'Initial commissioning reading',
        ]);

        $response->assertSessionHasNoErrors();

        $meter = Meter::where('meter_number', 'MTR-10001')->firstOrFail();

        $response->assertRedirect(route('meters.show', $meter))
            ->assertSessionHas('success', 'Meter created successfully');

        $this->assertDatabaseHas('meters', [
            'id' => $meter->id,
            'account_id' => $account->id,
            'resident_id' => $resident->id,
            'type' => 'analog',
            'meter_type' => 'individual',
            'location' => 'Utility room',
        ]);

        $this->assertDatabaseHas('meter_readings', [
            'meter_id' => $meter->id,
            'reading_value' => 125.50,
            'reader_id' => null,
            'notes' => 'Initial commissioning reading',
        ]);

        $this->assertSame(
            today()->toDateString(),
            $meter->readings()->firstOrFail()->reading_date->toDateString(),
        );
    }

    public function test_meter_creation_requires_a_location(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');
        $account = Account::factory()->create();
        $resident = Resident::factory()->create([
            'account_number' => $account->account_number,
            'status' => true,
        ]);

        $response = $this->actingAs($user)->from(route('meters.create'))->post(route('meters.store'), [
            'account_id' => $account->id,
            'resident_id' => $resident->id,
            'meter_number' => 'MTR-10002',
            'meter_name' => 'Main meter',
            'type' => 'water',
            'meter_type' => 'individual',
            'status' => 'active',
            'initial_reading' => '0',
        ]);

        $response->assertRedirect(route('meters.create'))
            ->assertSessionHasErrors('location');

        $this->assertDatabaseMissing('meters', [
            'meter_number' => 'MTR-10002',
        ]);

        $this->assertSame(0, MeterReading::count());
    }

    public function test_meter_can_be_created_without_a_linked_resident(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');
        $account = Account::factory()->create();

        $response = $this->actingAs($user)->post(route('meters.store'), [
            'account_id' => $account->id,
            'meter_number' => 'MTR-NO-RESIDENT',
            'meter_name' => 'Shared meter',
            'location' => 'Utility room',
            'type' => 'water',
            'meter_type' => 'individual',
            'status' => 'active',
            'initial_reading' => '0',
        ]);

        $meter = Meter::where('meter_number', 'MTR-NO-RESIDENT')->firstOrFail();

        $response->assertRedirect(route('meters.show', $meter))
            ->assertSessionHas('success', 'Meter created successfully');

        $this->assertDatabaseHas('meters', [
            'id' => $meter->id,
            'account_id' => $account->id,
            'resident_id' => null,
        ]);
    }
}
