<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("🚀 Provisioning Full SBMS Ecosystem Data...");

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
            'phone_number' => '+1234567890', 'status' => true, 'created_at' => now(), 'updated_at' => now()
        ], 'user_id');

        $driverUserId = DB::table('users')->insertGetId([
            'role' => 'driver', 'username' => 'john_driver', 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'email' => 'driver@sbms.com', 'password' => Hash::make('password123'),
            'phone_number' => '+1987654321', 'status' => true, 'created_at' => now(), 'updated_at' => now()
        ], 'user_id');

        $guardianUserId = DB::table('users')->insertGetId([
            'role' => 'guardian', 'username' => 'sarah_parent', 'first_name' => 'Sarah', 'last_name' => 'Connor',
            'gender' => 'female', 'email' => 'guardian@sbms.com', 'password' => Hash::make('password123'),
            'phone_number' => '+15550199', 'status' => true, 'created_at' => now(), 'updated_at' => now()
        ], 'user_id');

        $this->command->info("✅ System Users created.");

        // ----------------------------------------------------------------
        // PHASE 2: SEED PROFILE SUB-TABLES (WITH PROPER CONSTRAINTS)
        // ----------------------------------------------------------------
        
        // 1. Seed Drivers Profile (specifying 'id' as the PK returning name)
        try {
            $driverId = DB::table('drivers')->insertGetId([
                'user_id' => $driverUserId,
                'license_number' => 'DL-992384',
                'created_at' => now(),
                'updated_at' => now()
            ], 'id'); 
            $this->command->info("✅ Driver profile created successfully.");
        } catch (\Exception $e) {
            // Fallback to user_id if primary key name differs
            $driverId = $driverUserId;
        }

        // 2. Seed Guardians Profile (providing required 'guardian_code'!)
        try {
            $guardianId = DB::table('guardians')->insertGetId([
                'user_id' => $guardianUserId,
                'guardian_code' => 'GDN-8812', // Solves NOT-NULL violation!
                'address' => '742 Evergreen Terrace',
                'created_at' => now(),
                'updated_at' => now()
            ], 'guardian_id');
            $this->command->info("✅ Guardian profile created successfully.");
        } catch (\Exception $e) {
            // Fallback to trying 'id' if 'guardian_id' key isn't standard
            try {
                $guardianId = DB::table('guardians')->insertGetId([
                    'user_id' => $guardianUserId,
                    'guardian_code' => 'GDN-8812',
                    'address' => '742 Evergreen Terrace',
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
        // PHASE 3: LOGISTICS
        // ----------------------------------------------------------------
        $routeId = DB::table('routes')->insertGetId([
            'route_name' => 'Downtown Express A', 'start_location' => 'Main Depot Terminal',
            'end_location' => 'Oakwood Elementary School', 'estimated_duration' => 45,
            'created_at' => now(), 'updated_at' => now()
        ], 'route_id');

        $studentId = DB::table('students')->insertGetId([
            'first_name' => 'Alex', 'last_name' => 'Connor', 'gender' => 'male', 'student_code' => 'STU-9921',
            'date_of_birth' => '2016-04-12', 'grade_level' => 'Grade 3', 'pickup_add' => '742 Evergreen Terrace',
            'dropoff_add' => '100 Main Street School Grounds', 'created_at' => now(), 'updated_at' => now()
        ], 'student_id');

        // ----------------------------------------------------------------
        // PHASE 4: CONNECTING RELATIONSHIPS
        // ----------------------------------------------------------------
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
                    'route_id' => $routeId, 'student_id' => $studentId, 'stop_address' => '742 Evergreen Terrace', 'stop_order' => 1, 'created_at' => now(), 'updated_at' => now()
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

        $this->command->info("🎯 Ecosystem Seeding Complete!");
    }
}