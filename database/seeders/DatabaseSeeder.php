<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Resident;
use App\Models\Meter;
use App\Models\Billing;
use App\Models\Payment;
use App\Models\MeterReading;
use App\Models\Employee;
use App\Models\BillingDetail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Antony Kipingor as Admin

        $adminUser = User::factory()->create([
            'name' => 'Antony Kipingor',
            'email' => 'kipingor@gmail.com',
            'password' => Hash::make('deadenman80'),
            'profile_image' => fake()->imageUrl(200, 200, 'people'),
        ]);        

        // Define roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $accountant = Role::firstOrCreate(['name' => 'accountant']);
        $field_officer = Role::firstOrCreate(['name' => 'field_officer']);

        // Define permissions
        $permissions = [
            'manage-users', 'manage-residents', 'view-reports', 'manage-bills', 'process-payments', 'record-meter-readings'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        $admin->givePermissionTo(['manage-users', 'manage-residents',  'view-reports', 'manage-bills', 'process-payments', 'record-meter-readings']);
        $accountant->givePermissionTo(['view-reports', 'process-payments']);
        $field_officer->givePermissionTo(['record-meter-readings']);

        // Assign Admin role to Antony Kipingor
        $adminUser->assignRole($admin);

        // Create 20 residents with meters, readings, bills, and payments
        // Resident::factory(20)->create()->each(function ($resident) {
        //     $meter = Meter::factory()->create(['resident_id' => $resident->id]);

        //     $previousReading = 0;
        //     // Generate 12 monthly meter readings per meter
        //     for ($i = 1; $i <= 12; $i++) {
        //         $readingDate = Carbon::now()->subMonths(12 - $i);

        //         // Ensure the new reading is greater than the previous one
        //         $readingValue = $previousReading + rand(1, 50); // Increase by 1 to 50 units

        //         $reading = MeterReading::factory()->create([
        //             'meter_id' => $meter->id,
        //             'reading_date' => $readingDate,
        //             'reading_value' => $readingValue,
        //         ]);

        //         // $previousReading = MeterReading::where('meter_id', $reading->meter_id)->where('reading_date', '<', $reading->reading_date)->orderBy('reading_date', 'desc')->first()->reading_value ?? 0;
        //         $unitsUsed = $readingValue - $previousReading;

        //         // Generate a bill after each meter reading
        //         $bill = Billing::factory()->create([
        //             'meter_id' => $meter->id,
        //             'amount_due' => $unitsUsed * 300, // Example rate per unit
        //         ]);
               
        //         BillingDetail::factory()->create([
        //             'billing_id' => $bill->id,
        //             'previous_reading_value' => $previousReading,
        //             'current_reading_value' => $readingValue,
        //             'units_used' => $unitsUsed,
        //         ]);

        //         // Create a payment for some of the meters
                 
        //         if (rand(0, 1)) {
        //             $payment = Payment::factory()->create(['meter_id' => $meter->id, 'payment_date' => $readingDate->addDays(rand(1, 15))]);
        //             $payment->status = 'completed';
        //             $payment->save();
        //         } else {
        //             $payment = Payment::factory()->create(['meter_id' => $meter->id, 'payment_date' => null]);
        //             $payment->status = 'pending';
        //             $payment->save();
        //         }

        //         // Update previous reading for the next iteration
        //         $previousReading = $readingValue;
        //     }
        // });

        // Create 2 employees with role "field_officer"
        $employees = User::factory(2)->create()->each(function ($user) use ($field_officer) {
            $user->assignRole($field_officer);
            Employee::factory()->create(['user_id' => $user->id, 'position' => 'Field Officer']);
        });    
    }
}
