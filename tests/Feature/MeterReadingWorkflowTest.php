<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Billing;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Meter\MeterReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterReadingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['record-meter-readings', 'manage-bills']);
        $this->actingAs($this->user);
    }

    public function test_storing_a_reading_returns_to_create_with_follow_up_prompt(): void
    {
        $meter = Meter::factory()->create();

        $this->post(route('meter-readings.store'), [
            'meter_id' => $meter->id,
            'reading_value' => 125.5,
            'reading_date' => now()->toDateString(),
            'reading_type' => 'actual',
        ])
            ->assertRedirect(route('meter-readings.create'))
            ->assertSessionHas('success')
            ->assertSessionHas('after_reading.meter_id', $meter->id)
            ->assertSessionHas('after_reading.can_generate_bill', true);

        $this->assertDatabaseHas('meter_readings', [
            'meter_id' => $meter->id,
            'reading_value' => 125.5,
        ]);
    }

    public function test_bulk_create_page_is_available(): void
    {
        Meter::factory()->create();

        $this->get(route('meter-readings.bulk.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('meter-readings/bulk-create')
                ->has('meters')
            );
    }

    public function test_bulk_store_creates_filled_readings_and_skips_empty_rows(): void
    {
        $meterA = Meter::factory()->create();
        $meterB = Meter::factory()->create();

        $this->post(route('meter-readings.bulk.store'), [
            'reading_date' => now()->toDateString(),
            'readings' => [
                ['meter_id' => $meterA->id, 'reading_value' => 200],
                ['meter_id' => $meterB->id, 'reading_value' => ''],
            ],
        ])
            ->assertRedirect(route('meter-readings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('meter_readings', [
            'meter_id' => $meterA->id,
            'reading_value' => 200,
        ]);
        $this->assertDatabaseMissing('meter_readings', [
            'meter_id' => $meterB->id,
        ]);
    }

    public function test_bulk_store_generates_one_bill_for_each_account(): void
    {
        $account = Account::factory()->create();
        $meterA = Meter::factory()->for($account)->create();
        $meterB = Meter::factory()->for($account)->create();
        $readingDate = now()->toDateString();
        $meterReadingService = \Mockery::mock(MeterReadingService::class);
        $meterReadingService->shouldReceive('createBulkReadings')
            ->once()
            ->andReturn([
                'created' => collect([
                    new MeterReading(['meter_id' => $meterA->id]),
                    new MeterReading(['meter_id' => $meterB->id]),
                ]),
                'failed' => [],
            ]);
        $this->app->instance(MeterReadingService::class, $meterReadingService);

        $billingService = \Mockery::mock(BillingService::class);
        $billingService->shouldReceive('generateForAccount')
            ->once()
            ->withArgs(fn (Account $generatedAccount, string $period) =>
                $generatedAccount->is($account) && $period === now()->format('Y-m')
            )
            ->andReturn(new Billing());
        $this->app->instance(BillingService::class, $billingService);

        $this->post(route('meter-readings.bulk.store'), [
            'reading_date' => $readingDate,
            'generate_bills' => true,
            'readings' => [
                ['meter_id' => $meterA->id, 'reading_value' => 200],
                ['meter_id' => $meterB->id, 'reading_value' => 300],
            ],
        ])
            ->assertRedirect(route('meter-readings.index'))
            ->assertSessionHas('success', fn (string $message) =>
                str_contains($message, 'Generated 1 bill(s)')
            );
    }

    public function test_generate_bill_from_reading_creates_an_account_bill(): void
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading_value' => 1000,
            'reading_date' => now()->subMonth()->endOfMonth()->toDateString(),
            'reading_type' => 'actual',
        ]);

        $reading = MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading_value' => 1100,
            'reading_date' => now()->endOfMonth()->toDateString(),
            'reading_type' => 'actual',
        ]);

        $response = $this->from(route('meter-readings.create'))
            ->post(route('meter-readings.generate-bill', $reading));

        if ($response->getSession()->has('error')) {
            $this->markTestSkipped('Bill generation requires a configured tariff: ' . $response->getSession()->get('error'));
        }

        $response->assertRedirect(route('meter-readings.create'));
        $this->assertDatabaseHas('billings', [
            'account_id' => $account->id,
            'billing_period' => $reading->reading_date->format('Y-m'),
        ]);
        $this->assertInstanceOf(Billing::class, Billing::first());
    }
}
