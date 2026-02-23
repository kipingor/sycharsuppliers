<?php

namespace App\Console\Commands;

use App\Models\Tariff;
use App\Models\TariffRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateDefaultTariffCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tariff:create-default 
                            {--rate=300 : Base rate per unit}
                            {--force : Create even if tariffs exist}';

    /**
     * The console command description.
     */
    protected $description = 'Create a default tariff to prevent billing failures';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if tariffs already exist
        if (!$this->option('force')) {
            $existingCount = Tariff::where('status', 'active')->count();
            
            if ($existingCount > 0) {
                $this->info("Found {$existingCount} active tariff(s). Use --force to create anyway.");
                
                // List existing tariffs
                $tariffs = Tariff::where('status', 'active')->get();
                $this->table(
                    ['ID', 'Name', 'Type', 'Status', 'Effective From'],
                    $tariffs->map(fn($t) => [
                        $t->id,
                        $t->name,
                        $t->meter_type ?? 'All',
                        $t->status,
                        $t->effective_from->format('Y-m-d'),
                    ])
                );
                
                return Command::SUCCESS;
            }
        }

        $baseRate = $this->option('rate');

        $this->info("Creating default tariff with rate: {$baseRate} per unit");

        DB::transaction(function () use ($baseRate) {
            // Create tariff
            $tariff = Tariff::create([
                'name' => 'Default Water Tariff',
                'code' => 'DEFAULT_001',
                'type' => 'flat',
                'meter_type' => 'individual', // Applies to all meter types
                'status' => 'active',
                'is_default' => true,
                'effective_from' => now()->startOfMonth(),
                'effective_to' => null, // Open-ended
                'description' => 'Default flat-rate tariff created automatically. Update with tiered rates as needed.',
            ]);

            $this->info("✓ Created tariff: {$tariff->name} (ID: {$tariff->id})");

            // Create single flat rate
            $rate = TariffRate::create([
                'tariff_id' => $tariff->id,
                'tier_number' => 1,
                'min_units' => 0,
                'max_units' => null, // Unlimited
                'rate_per_unit' => $baseRate,
                'fixed_charge' => 0,
            ]);

            $this->info("✓ Created rate: {$rate->rate_per_unit} per unit for all consumption");

            // Display summary
            $this->newLine();
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📋 Default Tariff Created Successfully');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Tariff ID', $tariff->id],
                    ['Tariff Name', $tariff->name],
                    ['Tariff Code', $tariff->code],
                    ['Type', $tariff->type],
                    ['Meter Type', $tariff->meter_type ?? 'All'],
                    ['Rate per Unit', $rate->rate_per_unit],
                    ['Status', $tariff->status],
                    ['Default', $tariff->is_default ? 'Yes' : 'No'],
                    ['Effective From', $tariff->effective_from->format('Y-m-d')],
                ]
            );

            $this->newLine();
            $this->warn('⚠️  This is a basic flat-rate tariff.');
            $this->info('💡 To create tiered rates:');
            $this->info('   1. Go to Tariffs management in your admin panel');
            $this->info('   2. Edit this tariff');
            $this->info('   3. Add multiple rate tiers (e.g., 0-10 units, 11-50 units, 51+ units)');
        });

        // Clear tariff cache
        cache()->flush();
        $this->info('✓ Cache cleared');

        return Command::SUCCESS;
    }
}