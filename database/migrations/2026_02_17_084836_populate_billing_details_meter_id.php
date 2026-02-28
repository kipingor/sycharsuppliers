<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration populates meter_id in billing_details table.
     * Choose the appropriate strategy based on your data structure.
     */
    public function up(): void
    {
        // STRATEGY 1: Copy from billing.meter_id (if billing table has meter_id)
        // Uncomment if billing table has meter_id column
        
        if (Schema::hasColumn('billings', 'meter_id')) {
            DB::statement('
                UPDATE billing_details bd
                INNER JOIN billings b ON bd.billing_id = b.id
                SET bd.meter_id = b.meter_id
                WHERE bd.meter_id IS NULL
            ');
            
            Log::info('Populated meter_id from billing table');
        }
        

        // STRATEGY 2: Get meter from account (for accounts with single meter)
        // Use this if each account has only one meter
        DB::statement('
            UPDATE billing_details bd
            INNER JOIN billings b ON bd.billing_id = b.id
            INNER JOIN (
                SELECT account_id, MIN(id) as meter_id
                FROM meters
                WHERE status = "active"
                GROUP BY account_id
                HAVING COUNT(*) = 1
            ) m ON b.account_id = m.account_id
            SET bd.meter_id = m.meter_id
            WHERE bd.meter_id IS NULL
        ');
        
        Log::info('Populated meter_id for accounts with single meter');

        // STRATEGY 3: Match by meter readings (for complex scenarios)
        // This tries to match billing_details to meters based on the readings
        // Only use if readings in billing_details match meter_readings
        
        $unmatchedDetails = DB::table('billing_details')
            ->whereNull('meter_id')
            ->get();

        foreach ($unmatchedDetails as $detail) {
            // Try to find meter that has this reading
            $meterId = DB::table('meter_readings')
                ->where('reading_value', $detail->current_reading_value)
                ->orWhere('reading_value', $detail->previous_reading_value)
                ->value('meter_id');
            
            if ($meterId) {
                DB::table('billing_details')
                    ->where('id', $detail->id)
                    ->update(['meter_id' => $meterId]);
            }
        }
        
        Log::info('Matched meters by readings');
        

        // Check results
        $remaining = DB::table('billing_details')
            ->whereNull('meter_id')
            ->count();
        
        if ($remaining > 0) {
            Log::warning("{$remaining} billing_details still have NULL meter_id. You may need manual intervention or Strategy 3.");
        } else {
            Log::info('All billing_details now have meter_id populated.');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally clear meter_id values
        // Be careful - only uncomment if you want to revert
        
        DB::table('billing_details')
            ->update(['meter_id' => null]);
        
        Log::info('Cleared meter_id from billing_details');
    }
};