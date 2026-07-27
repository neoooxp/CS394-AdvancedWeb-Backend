<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("🚀 Provisioning Full SBMS Ecosystem Data (Phnom Penh, Cambodia)...");

        // ----------------------------------------------------------------
        // PHASE 0: WIPE REPO STATE (Deletes child tables first)
        // ----------------------------------------------------------------
        $tables = [
            'payments', 'ledgers', 'fee_structures', 'driver_schedules', 
            'attendance_logs', 'student_stops', 'students_stop', 
            'student_guardians', 'students', 'buses', 'routes', 
            'drivers', 'guardians', 'users'
        ];
        
        foreach ($tables as $table) {
            try { 
                DB::table($table)->delete(); 
            } catch (\Exception $e) {
                // Skip if table doesn't exist
            }
        }

        // ----------------------------------------------------------------
        // PHASE 1: CREATE SYSTEM USERS
        // ----------------------------------------------------------------
        $adminId = DB::table('users')->insertGetId([
            'role' => 'admin', 'username' => 'sbms_admin', 'first_name' => 'Alice', 'last_name' => 'Smith',
            'gender' => 'female', 'email' => 'admin@sbms.com', 'password' => Hash::make('password123'),
            'phone_number' => '+855 12 345 678', 'status' => true, 'created_at' => now(), 'updated_at' => now()
        ], 'user_id');

        $driverUserId = DB::table('users')->insertGetId([
            'role' => 'driver', 'username' => 'john_driver', 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'email' => 'driver@sbms.com', 'password' => Hash::make('password123'),
            'phone_number' => '+855 98 765 432', 'status' => true, 'created_at' => now(), 'updated_at' => now()
        ], 'user_id');

        $guardianUserId = DB::table('users')->insertGetId([
            'role' => 'guardian', 'username' => 'sarah_parent', 'first_name' => 'Sarah', 'last_name' => 'Connor',
            'gender' => 'female', 'email' => 'guardian@sbms.com', 'password' => Hash::make('password123'),
            'phone_number' => '+855 17 555 019', 'status' => true, 'created_at' => now(), 'updated_at' => now()
        ], 'user_id');

        $this->command->info("✅ System Users created.");

        // ----------------------------------------------------------------
        // PHASE 2: SEED PROFILE SUB-TABLES (WITH PROPER CONSTRAINTS)
        // ----------------------------------------------------------------
        
        // 1. Seed Drivers Profile
        try {
            $driverId = DB::table('drivers')->insertGetId([
                'user_id' => $driverUserId,
                'employee_id' => 1001,
                'license_number' => 'PP-DL-992384',
                'license_expiry_date' => now()->addYears(3)->toDateString(),
                'employment_status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ], 'id'); 
            $this->command->info("✅ Driver profile created successfully.");
        } catch (\Exception $e) {
            $this->command->error("❌ Driver Seed Error: " . $e->getMessage());
            $driverId = $driverUserId;
        }

        // 2. Seed Guardians Profile
        try {
            $guardianId = DB::table('guardians')->insertGetId([
                'user_id' => $guardianUserId,
                'guardian_code' => 'GDN-8812',
                'address' => 'St 310, Boeung Keng Kang 1 (BKK1), Phnom Penh',
                'created_at' => now(),
                'updated_at' => now()
            ], 'guardian_id');
            $this->command->info("✅ Guardian profile created successfully.");
        } catch (\Exception $e) {
            try {
                $guardianId = DB::table('guardians')->insertGetId([
                    'user_id' => $guardianUserId,
                    'guardian_code' => 'GDN-8812',
                    'address' => 'St 310, Boeung Keng Kang 1 (BKK1), Phnom Penh',
                    'created_at' => now(),
                    'updated_at' => now()
                ], 'id');
                $this->command->info("✅ Guardian profile created successfully (using 'id').");
            } catch (\Exception $subException) {
                $this->command->error("❌ Guardian Seed Error: " . $subException->getMessage());
                $guardianId = $guardianUserId;
            }
        }

        // ----------------------------------------------------------------
        // PHASE 3: LOGISTICS (Buses, Routes, Students)
        // ----------------------------------------------------------------
        $busesToSeed = [
            ['bus_number' => '101-A', 'plate_number' => 'Phnom Penh 3A-1011', 'capacity' => 60, 'model' => 'All American', 'manufacturer' => 'Blue Bird', 'year' => 2021, 'mileage' => 12500, 'availability_status' => 'Available'],
            ['bus_number' => '102-B', 'plate_number' => 'Phnom Penh 3B-2022', 'capacity' => 54, 'model' => 'Transit Liner', 'manufacturer' => 'Thomas Built', 'year' => 2020, 'mileage' => 18200, 'availability_status' => 'Available'],
            ['bus_number' => '103-C', 'plate_number' => 'Phnom Penh 3C-3033', 'capacity' => 48, 'model' => 'Vision', 'manufacturer' => 'Blue Bird', 'year' => 2019, 'mileage' => 24100, 'availability_status' => 'Maintenance'],
            ['bus_number' => '104-D', 'plate_number' => 'Phnom Penh 3D-4044', 'capacity' => 66, 'model' => 'Saf-T-Liner C2', 'manufacturer' => 'Thomas Built', 'year' => 2022, 'mileage' => 9400, 'availability_status' => 'Available'],
            ['bus_number' => '105-E', 'plate_number' => 'Phnom Penh 3E-5055', 'capacity' => 60, 'model' => 'All American', 'manufacturer' => 'Blue Bird', 'year' => 2023, 'mileage' => 5200, 'availability_status' => 'Available'],
        ];

        foreach ($busesToSeed as $busItem) {
            try {
                DB::table('buses')->insert(array_merge($busItem, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            } catch (\Exception $e) {}
        }
        $this->command->info("✅ Preseeded Fleet Buses created.");

        $routeId = DB::table('routes')->insertGetId([
            'route_name' => 'Route A1 - Norodom Blvd Express',
            'start_location' => 'Chroy Changvar Depot Terminal, Phnom Penh',
            'end_location' => 'International School of Phnom Penh (ISPP)',
            'estimated_duration' => 45,
            'driver_id' => $driverUserId,
            'created_at' => now(), 'updated_at' => now()
        ], 'route_id');

        $studentId = DB::table('students')->insertGetId([
            'first_name' => 'Alex', 'last_name' => 'Connor', 'gender' => 'male', 'student_code' => 'STU-9921',
            'date_of_birth' => '2016-04-12', 'grade_level' => 'Grade 3',
            'pickup_add' => 'St 310, Boeung Keng Kang 1 (BKK1), Phnom Penh',
            'dropoff_add' => 'Hun Sen Boulevard, ISPP Campus, Phnom Penh',
            'created_at' => now(), 'updated_at' => now()
        ], 'student_id');

        // ----------------------------------------------------------------
        // PHASE 4: CONNECTING RELATIONSHIPS
        // ----------------------------------------------------------------
        // Link Bus 101-A to Route
        $firstBus = DB::table('buses')->first();
        if ($firstBus) {
            DB::table('bus_routes')->insert([
                'bus_id' => $firstBus->bus_id,
                'route_id' => $routeId,
                'created_at' => now(),
            ]);
        }

        // Link Driver Schedule
        try {
            DB::table('driver_schedules')->insert([
                'driver_id' => $driverId,
                'shift_start_time' => now()->setTime(6, 30),
                'shift_end_time' => now()->setTime(14, 30),
                'is_available' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {}

        DB::table('student_guardians')->insert([
            'student_id' => $studentId, 
            'guardian_id' => $guardianId, 
            'relationship_type' => 'Mother', 
            'created_at' => now(), 
            'updated_at' => now()
        ]);
        $this->command->info("✅ Student-Guardian link created.");

        foreach (['students_stop', 'student_stops'] as $table) {
            try {
                DB::table($table)->insert([
                    'route_id' => $routeId,
                    'student_id' => $studentId,
                    'stop_address' => 'St 310, Boeung Keng Kang 1 (BKK1), Phnom Penh',
                    'stop_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $e) {}
        }

        // ----------------------------------------------------------------
        // PHASE 5: MONGODB CLOUD CONNECTION
        // ----------------------------------------------------------------
        try {
            DB::connection('mongodb')->table('maintenance_requests')->insert([
                'bus_id' => 1, 'driver_id' => $driverId, 'issue' => 'Rear door hydraulic seal wearing down.', 'status' => 'Pending', 'photos' => [], 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()
            ]);
            $this->command->info("✅ MongoDB Maintenance Log Seeded Successfully.");
        } catch (\Exception $e) {
            $this->command->warn("⚠️ MongoDB Skipped: " . $e->getMessage());
        }

        $this->command->info("🎯 Ecosystem Seeding Complete (Phnom Penh, Cambodia)!");
    }
}