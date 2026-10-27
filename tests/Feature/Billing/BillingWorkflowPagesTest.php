<?php

namespace Tests\Feature\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\User;
use App\Services\Billing\StatementGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingWorkflowPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('manage-bills');
        $this->actingAs($this->user);
    }

    public function test_bill_show_includes_previous_and_next_navigation(): void
    {
        $account = Account::factory()->create();
        $period = '2026-10';

        $first = Billing::factory()->create([
            'account_id' => $account->id,
            'billing_period' => $period,
            'status' => 'pending',
        ]);
        $second = Billing::factory()->create([
            'account_id' => Account::factory()->create()->id,
            'billing_period' => $period,
            'status' => 'pending',
        ]);
        $third = Billing::factory()->create([
            'account_id' => Account::factory()->create()->id,
            'billing_period' => $period,
            'status' => 'pending',
        ]);

        $this->get(route('billings.show', $second))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/show')
                ->where('navigation.previous_id', $first->id)
                ->where('navigation.next_id', $third->id)
                ->where('navigation.position', 2)
                ->where('navigation.total', 3)
            );
    }

    public function test_period_bills_can_be_downloaded_as_zip(): void
    {
        $period = '2026-10';
        $zipPath = storage_path('app/temp/test_bills.zip');

        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        file_put_contents($zipPath, 'zip');

        $this->mock(StatementGenerator::class, function ($mock) use ($period, $zipPath) {
            $mock->shouldReceive('generatePeriodBillsZip')
                ->once()
                ->with($period)
                ->andReturn($zipPath);
        });

        $this->get(route('billings.period.download', ['period' => $period]))
            ->assertOk()
            ->assertDownload("bills_{$period}.zip");
    }

    public function test_period_download_requires_a_period(): void
    {
        $this->get(route('billings.period.download'))
            ->assertSessionHasErrors('period');
    }
}
