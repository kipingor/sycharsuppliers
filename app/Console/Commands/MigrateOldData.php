<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MigrateOldData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-old-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from old DB to new schema';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::beginTransaction();

        try {
            $this->info('🔄 Disabling foreign key checks...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0;'); // ✅ Disable FK constraints

            // 1. Migrate customers → residents
            $this->info('Migrating customers to residents...');
            DB::table('residents')->truncate();

            $addresses = DB::connection('old')->table('addresses')->pluck('address', 'id'); // [address_id => address string]
            $customers = DB::connection('old')->table('customers')->get();
            // do {
            //     $phone = '254' . str_pad(random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT);
            // } while (DB::table('residents')->where('phone', $phone)->exists());            
            foreach ($customers as $c) {
                $originalPhone = $c->phone_number;

                // Normalize phone format (remove spaces, dashes, parentheses)
                $normalizedPhone = $originalPhone ? preg_replace('/\D+/', '', $originalPhone) : null;

                // If phone is empty, too short, or already exists — generate a new one
                if (!$normalizedPhone || strlen($normalizedPhone) < 9 || DB::table('residents')->where('phone', $normalizedPhone)->exists()) {
                    do {
                        $normalizedPhone = '254' . str_pad(random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT);
                    } while (DB::table('residents')->where('phone', $normalizedPhone)->exists());
                }
                DB::table('residents')->insert([
                    'id' => $c->id,
                    'name' => $c->name,
                    'email' => $c->email,
                    'phone' => $normalizedPhone,
                    'address' => $addresses[$c->id]->address ?? null, // fetch actual address text
                    'account_number' => Str::random(6), // 👈 generate account number
                    'created_at' => $c->created_at,
                    'updated_at' => $c->updated_at,
                ]);
            }

            // 2. Migrate water_meters → meters
            $this->info('Migrating water meters to meters...');
            DB::table('meters')->truncate();
            $addresses = DB::connection('old')->table('addresses')->pluck('address', 'id');
            $customers = DB::connection('old')->table('customers')->get()->keyBy('id');
            $meters = DB::connection('old')->table('water_meters')->get();
            
            foreach ($meters as $m) {
                $originalMeterNumber = $m->meter_number;

                // If meter_number is null/duplicate, generate a new one
                if (!$originalMeterNumber || DB::table('meters')->where('meter_number', $originalMeterNumber)->exists()) {
                    do {
                        $originalMeterNumber = (string) random_int(10000, 99999999); // or use Str::upper(Str::random(6));
                    } while (DB::table('meters')->where('meter_number', $originalMeterNumber)->exists());
                }
                DB::table('meters')->insert([
                    'id' => $m->id,
                    'resident_id' => $m->customer_id,
                    'meter_number' => $originalMeterNumber,
                    'meter_name' => $customers[$m->customer_id]->name ?? 'Unknown',
                    'location' => $addresses[$m->customer_id]->address ?? Str::random(6),
                    'installation_date' => $m->created_at,
                    'created_at' => $m->created_at,
                    'updated_at' => $m->updated_at,
                ]);
            }           

            // 3. Migrate bills → billing (using meter_id instead of resident_id)
            $this->info('Migrating bills to billing...');
            DB::table('billings')->truncate();

            $bills = DB::connection('old')->table('bills')->get();

            $statusMap = [
                1 => 'pending',
                2 => 'paid',
                3 => 'write off',
                4 => 'void',
                5 => 'partially paid',
            ];

            // Build a map of customer_id → meter_id from water_meters table
            $customerToMeter = DB::connection('old')->table('water_meters')
                ->select('id', 'customer_id')
                ->get()
                ->groupBy('customer_id')
                ->map(function ($group) {
                    return $group->first()->id ?? null; // use first meter if customer has more than one
                });

            foreach ($bills as $b) {
                $meterId = $customerToMeter[$b->customer_id] ?? null;

                if (!$meterId) {
                    $this->warn("⚠️  Skipping bill ID {$b->id} - no associated meter found for customer ID {$b->customer_id}");
                    continue;
                }

                DB::table('billings')->insert([
                    'id' => $b->id,
                    'meter_id' => $meterId,
                    'amount_due' => $b->amount,
                    'status' => $statusMap[$b->bill_status_id] ?? 'pending',
                    'created_at' => $b->created_at,
                    'updated_at' => $b->updated_at,
                ]);
            }

            // 4. Migrate bill_items → billing_details
            $this->info('Migrating bill items to billing details...');
            DB::table('billing_details')->truncate();
            $items = DB::connection('old')->table('bill_items')->get();
            foreach ($items as $item) {
                DB::table('billing_details')->insert([
                    'id' => $item->id,
                    'billing_id' => $item->bill_id,
                    'previous_reading_value' => $item->previous_reading,
                    'current_reading_value' => $item->current_reading,
                    'units_used' => ($item->current_reading - $item->previous_reading),
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }

            // 5. Migrate bill_payments → payments
            $this->info('Migrating bill payments to payments...');
            DB::table('payments')->truncate();
            // Fetch bills once and map [bill_id => meter_id]
            $customerToMeter = DB::connection('old')->table('water_meters')
                ->select('id', 'customer_id')
                ->get()
                ->groupBy('customer_id')
                ->map(fn($group) => $group->first()->id ?? null);

            // Build bill_id → meter_id mapping
            $billMeterMap = DB::connection('old')->table('bills')->get()
                ->mapWithKeys(function ($bill) use ($customerToMeter) {
                    $meterId = $customerToMeter[$bill->customer_id] ?? null;
                    return [$bill->id => $meterId];
                });
            $payments = DB::connection('old')->table('bill_payments')->get();
            foreach ($payments as $p) {
                $meterId = $billMeterMap[$p->bill_id] ?? null;

                // Skip if meter_id is missing
                if (!$meterId) {
                    $this->warn("⚠️  Skipping payment ID {$p->id} - no associated meter found for bill ID {$p->bill_id}");
                    continue;
                }
                DB::table('payments')->insert([
                    'id' => $p->id,
                    'meter_id' =>  $billMeterMap[$p->bill_id], // resolve meter_id from bill
                    'payment_date' => $p->created_at,
                    'amount' => $p->amount,
                    'method' => 'M-Pesa',
                    'transaction_id' => Str::random(10), // 👈 generate Transaction ID
                    'status' => 'completed',
                    'created_at' => $p->created_at,
                    'updated_at' => $p->updated_at,
                ]);
            }

            // 6. Migrate meter_readings
            $this->info('Migrating meter readings...');
            DB::table('meter_readings')->truncate();

            // Build a map of old water_meters.id to new meters.id
            $oldToNewMeterIds = DB::connection('old')->table('water_meters')->pluck('id')->mapWithKeys(function ($id) {
                return [$id => $id]; // Assuming IDs are preserved
            });

            $readings = DB::connection('old')->table('meter_readings')->get();

            foreach ($readings as $reading) {
                $meterId = $oldToNewMeterIds[$reading->meter_id] ?? null;

                if (!$meterId) {
                    $this->warn("⚠️  Skipping reading ID {$reading->id} - no associated meter found");
                    continue;
                }

                DB::table('meter_readings')->insert([
                    'id' => $reading->id,
                    'meter_id' => $meterId,
                    'reading_date' => Carbon::parse($reading->created_at)->toDateString(),
                    'reading_value' => $reading->reading,
                    'employee_id' => null,
                    'reading_type' => 'manual',
                    'created_at' => $reading->created_at,
                    'updated_at' => $reading->updated_at,
                ]);
            }
            
            $this->info('✅ Re-enabling foreign key checks...');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;'); // ✅ Re-enable FK constraints

            DB::commit();
            $this->info('✅ Migration completed successfully.');

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('❌ Migration failed: ' . $e->getMessage());
        }
    }
}
