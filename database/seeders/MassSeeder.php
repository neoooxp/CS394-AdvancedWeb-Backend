<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MassSeeder extends Seeder
{
    private string $hashedPassword;

    public function run(): void
    {
        // Disable query log to prevent memory bloat during massive inserts
        DB::disableQueryLog();

        $this->command->info("🚀 Starting optimized mass seeding of 1.2M+ database rows with complete ecosystem relationships...");

        // Pre-compute password hash ONCE for high performance
        $this->hashedPassword = Hash::make('password123');

        $startTime = microtime(true);

        // Ensure baseline fee structures exist
        $this->seedFeeStructures();

        $targets = [
            'users'                  => 50_000,
            'drivers'                => 20_000,  // 20k drivers + 20k users = 40k DB rows
            'guardians'              => 50_000,  // 50k guardians + 50k users = 100k DB rows
            'students'               => 200_000,
            'buses'                  => 30_000,
            'routes'                 => 30_000,  // Routes automatically assigned to driver user IDs
            'student_guardians'      => 150_000, // Student to guardian links
            'students_stop'          => 100_000, // Student to route stop links
            'bus_routes'             => 30_000,  // Bus to route links
            'driver_bus_assignments' => 20_000,  // Driver to bus assignments
            'driver_schedules'       => 20_000,  // Driver shift schedules
            'student_fee_assignment' => 100_000, // Student fee tier assignments
            'daily_attendance'       => 200_000,
            'invoices'               => 100_000,
            'payments'               => 50_000,
        ];

        $totalDbRowsInserted = 0;

        foreach ($targets as $table => $count) {
            $tableStartTime = microtime(true);
            $this->command->info("   Seeding {$table} ({$count} units)...");

            switch ($table) {
                case 'users':
                    $this->seedUsers($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'drivers':
                    $this->seedDrivers($count);
                    $totalDbRowsInserted += ($count * 2);
                    break;
                case 'guardians':
                    $this->seedGuardians($count);
                    $totalDbRowsInserted += ($count * 2);
                    break;
                case 'students':
                    $this->seedStudents($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'buses':
                    $this->seedBuses($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'routes':
                    $this->seedRoutes($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'student_guardians':
                    $this->seedStudentGuardians($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'students_stop':
                    $this->seedStudentStops($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'bus_routes':
                    $this->seedBusRoutes($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'driver_bus_assignments':
                    $this->seedDriverBusAssignments($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'driver_schedules':
                    $this->seedDriverSchedules($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'student_fee_assignment':
                    $this->seedStudentFeeAssignments($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'daily_attendance':
                    $this->seedDailyAttendance($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'invoices':
                    $this->seedInvoices($count);
                    $totalDbRowsInserted += $count;
                    break;
                case 'payments':
                    $this->seedPayments($count);
                    $totalDbRowsInserted += $count;
                    break;
            }

            $tableElapsed = round(microtime(true) - $tableStartTime, 2);
            $this->command->info("   ✔ Finished {$table} in {$tableElapsed}s");
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->command->info("✅ Mass seeding complete! Total database rows inserted: {$totalDbRowsInserted} in {$elapsed}s");
    }

    private function seedFeeStructures(): void
    {
        if (DB::table('fee_structure')->count() === 0) {
            DB::table('fee_structure')->insert([
                ['fee_name' => 'Standard Route (Monthly)', 'base_amount' => 150.00, 'discount_percentage' => 0.00, 'created_at' => now(), 'updated_at' => now()],
                ['fee_name' => 'Special Ed (Monthly)', 'base_amount' => 220.00, 'discount_percentage' => 0.00, 'created_at' => now(), 'updated_at' => now()],
                ['fee_name' => 'Field Trip (Hourly)', 'base_amount' => 45.00, 'discount_percentage' => 0.00, 'created_at' => now(), 'updated_at' => now()],
                ['fee_name' => 'Late Fee Penalty', 'base_amount' => 25.00, 'discount_percentage' => 0.00, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    private function seedUsers(int $count): void
    {
        $firstNames = ['Sophia', 'Liam', 'Emma', 'Noah', 'Olivia', 'Ethan', 'Ava', 'Mason', 'Isabella', 'William', 'Mia', 'James', 'Charlotte', 'Benjamin', 'Amelia', 'Lucas', 'Harper', 'Henry', 'Evelyn', 'Alexander'];
        $lastNames = ['Kim', 'Hou', 'Pov', 'Sok', 'Chet', 'Vann', 'Ly', 'Heang', 'Sokhom', 'Phal', 'Theng', 'Rith', 'Serey', 'Chhorn', 'Mony', 'Sereypheap', 'Bun', 'Ros', 'Tep', 'Srey'];
        $roles = ['admin', 'driver', 'guardian'];
        $genders = ['male', 'female'];

        $users = [];
        $now = now();
        $chunkSize = 2500;

        for ($i = 0; $i < $count; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];

            $users[] = [
                'role' => $roles[array_rand($roles)],
                'username' => strtolower($firstName) . '.' . strtolower($lastName) . $i . '_' . uniqid(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $genders[array_rand($genders)],
                'email' => strtolower($firstName) . $i . '_' . uniqid() . '@sbms.com',
                'password' => $this->hashedPassword,
                'status' => true,
                'phone_number' => '+855 ' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(100, 999),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($users) >= $chunkSize) {
                DB::transaction(function () use (&$users) {
                    DB::table('users')->insert($users);
                });
                $users = [];
            }
        }

        if (!empty($users)) {
            DB::transaction(function () use (&$users) {
                DB::table('users')->insert($users);
            });
        }
    }

    private function seedDrivers(int $count): void
    {
        $firstNames = ['Kunthea', 'Rithy', 'Sreysour', 'Mey', 'Vanha', 'Dara', 'Sokha', 'Vicheka', 'Narith', 'Pisey'];
        $lastNames = ['Svay', 'Tep', 'Chhay', 'Horm', 'Kong', 'Touch', 'Srun', 'Lim', 'Hok', 'You'];
        $statuses = ['Active', 'Active', 'Active', 'On Leave', 'Suspended'];
        $now = now();
        $chunkSize = 2500;

        $processed = 0;

        while ($processed < $count) {
            $currentBatchSize = min($chunkSize, $count - $processed);
            $startUserId = (DB::table('users')->max('user_id') ?? 0) + 1;

            $userBatch = [];
            $driverBatch = [];

            for ($i = 0; $i < $currentBatchSize; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $userId = $startUserId + $i;

                $userBatch[] = [
                    'user_id' => $userId,
                    'role' => 'driver',
                    'username' => 'driver_' . ($processed + $i) . '_' . uniqid(),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'gender' => rand(0, 1) ? 'male' : 'female',
                    'email' => 'driver' . ($processed + $i) . '_' . uniqid() . '@sbms.com',
                    'password' => $this->hashedPassword,
                    'status' => true,
                    'phone_number' => '+855 ' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(100, 999),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $driverBatch[] = [
                    'user_id' => $userId,
                    'employee_id' => 1001 + $processed + $i,
                    'license_number' => 'PP-DL-' . rand(100000, 999999),
                    'license_expiry_date' => $now->copy()->addYears(rand(1, 5))->toDateString(),
                    'employment_status' => $statuses[array_rand($statuses)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::transaction(function () use (&$userBatch, &$driverBatch) {
                DB::table('users')->insert($userBatch);
                DB::table('drivers')->insert($driverBatch);
            });

            $processed += $currentBatchSize;
        }
    }

    private function seedGuardians(int $count): void
    {
        $firstNames = ['Sophia', 'Liam', 'Emma', 'Noah', 'Olivia', 'Ethan', 'Ava', 'Mason', 'Isabella', 'William'];
        $lastNames = ['Kim', 'Hou', 'Pov', 'Sok', 'Chet', 'Vann', 'Ly', 'Heang', 'Sokhom', 'Phal'];
        $areas = ['BKK1', 'Toul Kork', 'Chamkar Mon', 'Boeung Keng Kang', 'Chroy Changvar', 'Meanchey', 'Kamp Cham', 'Ta Khmao', 'Khan Sensok', 'Khan Por Senchey'];
        $now = now();
        $chunkSize = 2500;

        $processed = 0;

        while ($processed < $count) {
            $currentBatchSize = min($chunkSize, $count - $processed);
            $startUserId = (DB::table('users')->max('user_id') ?? 0) + 1;

            $userBatch = [];
            $guardianBatch = [];

            for ($i = 0; $i < $currentBatchSize; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $userId = $startUserId + $i;

                $userBatch[] = [
                    'user_id' => $userId,
                    'role' => 'guardian',
                    'username' => 'guardian_' . ($processed + $i) . '_' . uniqid(),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'gender' => rand(0, 1) ? 'male' : 'female',
                    'email' => 'guardian' . ($processed + $i) . '_' . uniqid() . '@sbms.com',
                    'password' => $this->hashedPassword,
                    'status' => true,
                    'phone_number' => '+855 ' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(100, 999),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $guardianBatch[] = [
                    'user_id' => $userId,
                    'guardian_code' => 'GDN-' . str_pad($processed + $i + 10000, 7, '0', STR_PAD_LEFT),
                    'address' => rand(1, 500) . ' Street ' . rand(1, 300) . ', ' . $areas[array_rand($areas)] . ', Phnom Penh',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::transaction(function () use (&$userBatch, &$guardianBatch) {
                DB::table('users')->insert($userBatch);
                DB::table('guardians')->insert($guardianBatch);
            });

            $processed += $currentBatchSize;
        }
    }

    private function seedStudents(int $count): void
    {
        $firstNames = ['Kunthea', 'Rithy', 'Sreysour', 'Mey', 'Vanha', 'Dara', 'Sokha', 'Vicheka', 'Narith', 'Pisey', 'Kimsroy', 'Sokheng', 'Visal', 'Nika', 'Bopha', 'Raksmey', 'Sovann', 'Sreyleak', 'Bunthoeun', 'Narin'];
        $lastNames = ['Svay', 'Tep', 'Chhay', 'Horm', 'Kong', 'Touch', 'Srun', 'Lim', 'Hok', 'You', 'Chou', 'Suong', 'Chea', 'Keo', 'Phork', 'Nak', 'Chheang', 'Thay', 'Eang', 'Lor'];
        $grades = ['Nursery', 'Kindergarten 1', 'Kindergarten 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
        $statuses = ['Active', 'Active', 'Active', 'Active', 'Inactive', 'Graduated', 'Suspended'];

        $students = [];
        $now = now();
        $chunkSize = 2500;

        for ($i = 0; $i < $count; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];

            $students[] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => rand(0, 1) ? 'male' : 'female',
                'student_code' => 'STU-' . str_pad($i + 10000, 8, '0', STR_PAD_LEFT),
                'date_of_birth' => $now->copy()->subYears(rand(4, 18))->subDays(rand(0, 365))->toDateString(),
                'grade_level' => $grades[array_rand($grades)],
                'pickup_add' => rand(1, 500) . ' Street ' . rand(1, 300) . ', Phnom Penh',
                'dropoff_add' => rand(1, 500) . ' Street ' . rand(1, 300) . ', Phnom Penh',
                'enrollment_status' => $statuses[array_rand($statuses)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($students) >= $chunkSize) {
                DB::transaction(function () use (&$students) {
                    DB::table('students')->insert($students);
                });
                $students = [];
            }
        }

        if (!empty($students)) {
            DB::transaction(function () use (&$students) {
                DB::table('students')->insert($students);
            });
        }
    }

    private function seedBuses(int $count): void
    {
        $models = ['All American', 'Transit Liner', 'Vision', 'Saf-T-Liner C2', 'Micro Bird', 'Diamond', 'Patriot', 'Boss', 'Champion'];
        $manufacturers = ['Blue Bird', 'Thomas Built', 'Ford', 'IC Bus', 'Lion', 'Van Hool', 'Proterra', 'Collins'];
        $statuses = ['Available', 'Available', 'Available', 'Maintenance', 'In Transit', 'Retired'];

        $buses = [];
        $now = now();
        $chunkSize = 2500;

        for ($i = 0; $i < $count; $i++) {
            $buses[] = [
                'bus_number' => 'BUS-' . str_pad($i + 1000, 6, '0', STR_PAD_LEFT),
                'plate_number' => 'Phnom Penh ' . chr(rand(65, 90)) . rand(1000, 9999),
                'capacity' => rand(20, 80),
                'model' => $models[array_rand($models)],
                'manufacturer' => $manufacturers[array_rand($manufacturers)],
                'year' => rand(2015, 2025),
                'mileage' => rand(1000, 150000),
                'availability_status' => $statuses[array_rand($statuses)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($buses) >= $chunkSize) {
                DB::transaction(function () use (&$buses) {
                    DB::table('buses')->insert($buses);
                });
                $buses = [];
            }
        }

        if (!empty($buses)) {
            DB::transaction(function () use (&$buses) {
                DB::table('buses')->insert($buses);
            });
        }
    }

    private function seedRoutes(int $count): void
    {
        $districts = ['BKK1', 'Toul Kork', 'Chamkar Mon', 'Boeung Keng Kang', 'Chroy Changvar', 'Meanchey', 'Sensok', 'Por Senchey', 'Chbar Ampov', 'Russey Keo', 'Dangkao'];
        $startHubs = ['Chroy Changvar Depot', 'Touk Kork Market Terminal', 'Central Market Station', 'St 271 Hub', 'St 215 Junction', 'Norodom Blvd Depot', 'Russian Blvd Station', 'Hun Sen Blvd Terminal', 'Chbar Ampov Depot', 'Veng Sreng Hub'];
        $schoolDestinations = ['International School of Phnom Penh (ISPP)', 'Northbridge International School', 'Canadian International School (CIS)', 'Zaman International School', 'Logos International School', 'Footprints International School', 'Golden Apple Academy', 'Amberwood International School'];
        $routeTypes = ['Express', 'Direct Shuttle', 'Morning Loop', 'Afternoon Line', 'Commuter'];

        $driverUserIds = DB::table('users')->where('role', 'driver')->pluck('user_id')->toArray();

        $routes = [];
        $now = now();
        $chunkSize = 2500;

        for ($i = 0; $i < $count; $i++) {
            $driverId = !empty($driverUserIds) ? $driverUserIds[array_rand($driverUserIds)] : null;

            $routes[] = [
                'route_name' => 'Route ' . chr(rand(65, 90)) . rand(1, 999) . ' - ' . $districts[array_rand($districts)] . ' ' . $routeTypes[array_rand($routeTypes)],
                'start_location' => 'St ' . rand(1, 500) . ', ' . $startHubs[array_rand($startHubs)] . ', Phnom Penh',
                'end_location' => $schoolDestinations[array_rand($schoolDestinations)] . ', Phnom Penh',
                'estimated_duration' => rand(15, 90),
                'driver_id' => $driverId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($routes) >= $chunkSize) {
                DB::transaction(function () use (&$routes) {
                    DB::table('routes')->insert($routes);
                });
                $routes = [];
            }
        }

        if (!empty($routes)) {
            DB::transaction(function () use (&$routes) {
                DB::table('routes')->insert($routes);
            });
        }
    }

    private function seedStudentGuardians(int $count): void
    {
        $relationships = ['Father', 'Mother', 'Legal Guardian', 'Grandparent', 'Uncle', 'Aunt'];
        $now = now();
        $chunkSize = 2500;

        $maxGuardianId = DB::table('guardians')->max('guardian_id') ?? 1;
        $maxStudentId = DB::table('students')->max('student_id') ?? 1;

        $studentGuardians = [];

        for ($i = 0; $i < $count; $i++) {
            $guardianId = rand(1, $maxGuardianId);
            $studentId = rand(1, $maxStudentId);

            $studentGuardians[] = [
                'guardian_id' => $guardianId,
                'student_id' => $studentId,
                'relationship_type' => $relationships[array_rand($relationships)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($studentGuardians) >= $chunkSize) {
                DB::transaction(function () use (&$studentGuardians) {
                    DB::table('student_guardians')->insert($studentGuardians);
                });
                $studentGuardians = [];
            }
        }

        if (!empty($studentGuardians)) {
            DB::transaction(function () use (&$studentGuardians) {
                DB::table('student_guardians')->insert($studentGuardians);
            });
        }
    }

    private function seedStudentStops(int $count): void
    {
        $now = now();
        $chunkSize = 2500;

        $maxRouteId = DB::table('routes')->max('route_id') ?? 1;
        $maxStudentId = DB::table('students')->max('student_id') ?? 1;

        $studentStops = [];

        for ($i = 0; $i < $count; $i++) {
            $studentId = rand(1, $maxStudentId);
            $routeId = rand(1, $maxRouteId);

            $studentStops[] = [
                'student_id' => $studentId,
                'route_id' => $routeId,
                'stop_address' => rand(1, 500) . ' Street ' . rand(1, 300) . ', Phnom Penh',
                'stop_order' => rand(1, 15),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($studentStops) >= $chunkSize) {
                DB::transaction(function () use (&$studentStops) {
                    DB::table('students_stop')->insert($studentStops);
                });
                $studentStops = [];
            }
        }

        if (!empty($studentStops)) {
            DB::transaction(function () use (&$studentStops) {
                DB::table('students_stop')->insert($studentStops);
            });
        }
    }

    private function seedBusRoutes(int $count): void
    {
        $now = now();
        $chunkSize = 2500;

        $maxBusId = DB::table('buses')->max('bus_id') ?? 1;
        $maxRouteId = DB::table('routes')->max('route_id') ?? 1;

        $busRoutes = [];

        for ($i = 0; $i < $count; $i++) {
            $busRoutes[] = [
                'bus_id' => rand(1, $maxBusId),
                'route_id' => rand(1, $maxRouteId),
                'created_at' => $now,
            ];

            if (count($busRoutes) >= $chunkSize) {
                DB::transaction(function () use (&$busRoutes) {
                    DB::table('bus_routes')->insert($busRoutes);
                });
                $busRoutes = [];
            }
        }

        if (!empty($busRoutes)) {
            DB::transaction(function () use (&$busRoutes) {
                DB::table('bus_routes')->insert($busRoutes);
            });
        }
    }

    private function seedDriverBusAssignments(int $count): void
    {
        $statuses = ['Active', 'Active', 'Completed', 'Assigned'];
        $now = now();
        $chunkSize = 2500;

        $maxDriverId = DB::table('drivers')->max('id') ?? 1;
        $maxBusId = DB::table('buses')->max('bus_id') ?? 1;

        $assignments = [];

        for ($i = 0; $i < $count; $i++) {
            $assignments[] = [
                'driver_id' => rand(1, $maxDriverId),
                'bus_id' => rand(1, $maxBusId),
                'assigned_date' => $now->copy()->subDays(rand(0, 90))->toDateString(),
                'status' => $statuses[array_rand($statuses)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($assignments) >= $chunkSize) {
                DB::transaction(function () use (&$assignments) {
                    DB::table('driver_bus_assignments')->insert($assignments);
                });
                $assignments = [];
            }
        }

        if (!empty($assignments)) {
            DB::transaction(function () use (&$assignments) {
                DB::table('driver_bus_assignments')->insert($assignments);
            });
        }
    }

    private function seedDriverSchedules(int $count): void
    {
        $now = now();
        $chunkSize = 2500;

        $maxDriverId = DB::table('drivers')->max('id') ?? 1;
        $schedules = [];

        for ($i = 0; $i < $count; $i++) {
            $driverId = rand(1, $maxDriverId);
            $startTime = $now->copy()->subDays(rand(0, 30))->setTime(rand(6, 8), 0, 0);
            $endTime = $startTime->copy()->addHours(8);

            $schedules[] = [
                'driver_id' => $driverId,
                'shift_start_time' => $startTime->toDateTimeString(),
                'shift_end_time' => $endTime->toDateTimeString(),
                'is_available' => rand(0, 1),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($schedules) >= $chunkSize) {
                DB::transaction(function () use (&$schedules) {
                    DB::table('driver_schedules')->insert($schedules);
                });
                $schedules = [];
            }
        }

        if (!empty($schedules)) {
            DB::transaction(function () use (&$schedules) {
                DB::table('driver_schedules')->insert($schedules);
            });
        }
    }

    private function seedStudentFeeAssignments(int $count): void
    {
        $feeIds = DB::table('fee_structure')->pluck('fee_structure_id')->toArray();
        if (empty($feeIds)) return;

        $maxStudentId = DB::table('students')->max('student_id') ?? 1;
        $chunkSize = 2500;
        $assignments = [];
        $assignedPairs = [];

        for ($i = 0; $i < $count; $i++) {
            $studentId = rand(1, $maxStudentId);
            $feeId = $feeIds[array_rand($feeIds)];
            $key = "{$studentId}_{$feeId}";

            if (isset($assignedPairs[$key])) continue;
            $assignedPairs[$key] = true;

            $assignments[] = [
                'student_id' => $studentId,
                'fee_structure_id' => $feeId,
            ];

            if (count($assignments) >= $chunkSize) {
                DB::transaction(function () use (&$assignments) {
                    DB::table('student_fee_assignment')->insertOrIgnore($assignments);
                });
                $assignments = [];
            }
        }

        if (!empty($assignments)) {
            DB::transaction(function () use (&$assignments) {
                DB::table('student_fee_assignment')->insertOrIgnore($assignments);
            });
        }
    }

    private function seedDailyAttendance(int $count): void
    {
        $statuses = ['Boarded', 'Boarded', 'Boarded', 'Dropped Off', 'Absent', 'Absent', 'Late'];
        $now = now();
        $chunkSize = 2500;

        $maxStudentId = DB::table('students')->max('student_id') ?? 1;
        $driverUserIds = DB::table('users')->where('role', 'driver')->pluck('user_id')->toArray();
        $fallbackUserId = !empty($driverUserIds) ? $driverUserIds[0] : 1;

        $attendances = [];

        for ($i = 0; $i < $count; $i++) {
            $studentId = rand(1, $maxStudentId);
            $date = $now->copy()->subDays(rand(0, 365))->toDateString();
            $recordedBy = !empty($driverUserIds) ? $driverUserIds[array_rand($driverUserIds)] : $fallbackUserId;

            $attendances[] = [
                'student_id' => $studentId,
                'date' => $date,
                'status' => $statuses[array_rand($statuses)],
                'boarding_time' => rand(0, 1) ? $now->copy()->setTime(rand(6, 8), rand(0, 59))->toDateTimeString() : null,
                'drop_off_time' => rand(0, 1) ? $now->copy()->setTime(rand(14, 17), rand(0, 59))->toDateTimeString() : null,
                'pickup_location' => rand(0, 1) ? (string) rand(1, 500) : null,
                'recorded_by' => $recordedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($attendances) >= $chunkSize) {
                DB::transaction(function () use (&$attendances) {
                    DB::table('daily_attendance')->insert($attendances);
                });
                $attendances = [];
            }
        }

        if (!empty($attendances)) {
            DB::transaction(function () use (&$attendances) {
                DB::table('daily_attendance')->insert($attendances);
            });
        }
    }

    private function seedInvoices(int $count): void
    {
        $statuses = ['Unpaid', 'Unpaid', 'Paid', 'Overdue', 'Pending'];
        $now = now();
        $chunkSize = 2500;

        $maxGuardianId = DB::table('guardians')->max('guardian_id') ?? 1;
        $invoices = [];

        for ($i = 0; $i < $count; $i++) {
            $guardianId = rand(1, $maxGuardianId);
            $totalAmount = rand(50, 500) * 1000;

            $invoices[] = [
                'guardian_id' => $guardianId,
                'invoice_date' => $now->copy()->subDays(rand(0, 180))->toDateString(),
                'due_date' => $now->copy()->addDays(rand(15, 60))->toDateString(),
                'total_amount' => $totalAmount,
                'status' => $statuses[array_rand($statuses)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($invoices) >= $chunkSize) {
                DB::transaction(function () use (&$invoices) {
                    DB::table('invoices')->insert($invoices);
                });
                $invoices = [];
            }
        }

        if (!empty($invoices)) {
            DB::transaction(function () use (&$invoices) {
                DB::table('invoices')->insert($invoices);
            });
        }
    }

    private function seedPayments(int $count): void
    {
        $methods = ['Cash', 'Bank Transfer', 'ABA Pay', 'ACLEDA Pay', 'Credit Card', 'Wallet'];
        $now = now();
        $chunkSize = 2500;

        $maxInvoiceId = DB::table('invoices')->max('invoice_id') ?? 1;
        $payments = [];

        for ($i = 0; $i < $count; $i++) {
            $invoiceId = rand(1, $maxInvoiceId);

            $payments[] = [
                'invoice_id' => $invoiceId,
                'payment_date' => $now->copy()->subDays(rand(0, 180))->toDateString(),
                'amount_paid' => rand(20, 200) * 1000,
                'payment_method' => $methods[array_rand($methods)],
                'transaction_reference' => 'TXN-' . strtoupper(uniqid()) . '-' . rand(1000, 9999),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($payments) >= $chunkSize) {
                DB::transaction(function () use (&$payments) {
                    DB::table('payments')->insert($payments);
                });
                $payments = [];
            }
        }

        if (!empty($payments)) {
            DB::transaction(function () use (&$payments) {
                DB::table('payments')->insert($payments);
            });
        }
    }
}
