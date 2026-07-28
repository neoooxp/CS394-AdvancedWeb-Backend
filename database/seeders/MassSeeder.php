<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MassSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("🚀 Starting mass seeding of 1 million rows...");

        $targetTotal = 500_000;
        $chunkSize = 5000;
        $counts = [
            'users' => 0,
            'students' => 0,
            'buses' => 0,
            'routes' => 0,
            'daily_attendance' => 0,
            'invoices' => 0,
            'payments' => 0,
        ];

        $distribution = [
            'users' => 0.10,
            'drivers' => 0.02,
            'guardians' => 0.08,
            'students' => 0.25,
            'buses' => 0.02,
            'routes' => 0.02,
            'daily_attendance' => 0.35,
            'invoices' => 0.10,
            'payments' => 0.04,
        ];

        $inserted = 0;
        $startTime = microtime(true);

        while ($inserted < $targetTotal) {
            $remaining = $targetTotal - $inserted;
            $batchSize = min($chunkSize, $remaining);

            foreach ($distribution as $table => $ratio) {
                $targetForTable = (int) ($targetTotal * $ratio);
                $currentInTable = $counts[$table];

                if ($currentInTable >= $targetForTable) {
                    continue;
                }

                $toInsert = min(
                    (int) ($batchSize * $ratio),
                    $targetForTable - $currentInTable
                );

                if ($toInsert <= 0) {
                    continue;
                }

                $this->seedTable($table, $toInsert);
                $counts[$table] += $toInsert;
            }

            $inserted += $batchSize;
            $this->command->info("   Inserted {$inserted} / {$targetTotal} rows...");
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->command->info("✅ Mass seeding complete! {$targetTotal} rows in {$elapsed}s");
        $this->command->info("   Distribution: " . json_encode($counts));
    }

    private function seedTable(string $table, int $count): void
    {
        switch ($table) {
            case 'users':
                $this->seedUsers($count);
                break;
            case 'drivers':
                $this->seedDrivers($count);
                break;
            case 'guardians':
                $this->seedGuardians($count);
                break;
            case 'students':
                $this->seedStudents($count);
                break;
            case 'buses':
                $this->seedBuses($count);
                break;
            case 'routes':
                $this->seedRoutes($count);
                break;
            case 'daily_attendance':
                $this->seedDailyAttendance($count);
                break;
            case 'invoices':
                $this->seedInvoices($count);
                break;
            case 'payments':
                $this->seedPayments($count);
                break;
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

        for ($i = 0; $i < $count; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $role = $roles[array_rand($roles)];

            $users[] = [
                'role' => $role,
                'username' => strtolower($firstName) . '.' . strtolower($lastName) . $i . '_' . uniqid(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $genders[array_rand($genders)],
                'email' => strtolower($firstName) . $i . '_' . uniqid() . '@sbms.com',
                'password' => Hash::make('password123'),
                'status' => true,
                'phone_number' => '+855 ' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(100, 999),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($users) >= 1000) {
                DB::table('users')->insert($users);
                $users = [];
            }
        }

        if (!empty($users)) {
            DB::table('users')->insert($users);
        }
    }

    private function seedDrivers(int $count): void
    {
        $firstNames = ['Kunthea', 'Rithy', 'Sreysour', 'Mey', 'Vanha', 'Dara', 'Sokha', 'Vicheka', 'Narith', 'Pisey'];
        $lastNames = ['Svay', 'Tep', 'Chhay', 'Horm', 'Kong', 'Touch', 'Srun', 'Lim', 'Hok', 'You'];
        $statuses = ['Active', 'Active', 'Active', 'On Leave', 'Suspended'];
        $now = now();

        $drivers = [];

        for ($i = 0; $i < $count; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];

            $userId = DB::table('users')->insertGetId([
                'role' => 'driver',
                'username' => 'driver_' . $i . '_' . uniqid(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => rand(0, 1) ? 'male' : 'female',
                'email' => 'driver' . $i . '_' . uniqid() . '@sbms.com',
                'password' => Hash::make('password123'),
                'status' => true,
                'phone_number' => '+855 ' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(100, 999),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            try {
                $driverId = DB::table('drivers')->insertGetId([
                    'user_id' => $userId,
                    'employee_id' => 1001 + $i,
                    'license_number' => 'PP-DL-' . rand(100000, 999999),
                    'license_expiry_date' => $now->copy()->addYears(rand(1, 5))->toDateString(),
                    'employment_status' => $statuses[array_rand($statuses)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'id');
            } catch (\Exception $e) {
                try {
                    $driverId = DB::table('drivers')->insertGetId([
                        'user_id' => $userId,
                        'employee_id' => 1001 + $i,
                        'license_number' => 'PP-DL-' . rand(100000, 999999),
                        'license_expiry_date' => $now->copy()->addYears(rand(1, 5))->toDateString(),
                        'employment_status' => $statuses[array_rand($statuses)],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], 'driver_id');
                } catch (\Exception $e2) {
                    continue;
                }
            }
        }
    }

    private function seedGuardians(int $count): void
    {
        $firstNames = ['Sophia', 'Liam', 'Emma', 'Noah', 'Olivia', 'Ethan', 'Ava', 'Mason', 'Isabella', 'William'];
        $lastNames = ['Kim', 'Hou', 'Pov', 'Sok', 'Chet', 'Vann', 'Ly', 'Heang', 'Sokhom', 'Phal'];
        $areas = ['BKK1', 'Toul Kork', 'Chamkar Dor', 'Boeung Keng Kong', 'Chroy Changvar', 'Meanchey', 'Kamp Cham', 'Ta Khmao', 'Khan Sensok', 'Khan Por Senchey'];
        $now = now();

        for ($i = 0; $i < $count; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];

            $userId = DB::table('users')->insertGetId([
                'role' => 'guardian',
                'username' => 'guardian_' . $i . '_' . uniqid(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => rand(0, 1) ? 'male' : 'female',
                'email' => 'guardian' . $i . '_' . uniqid() . '@sbms.com',
                'password' => Hash::make('password123'),
                'status' => true,
                'phone_number' => '+855 ' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(100, 999),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            try {
                DB::table('guardians')->insert([
                    'user_id' => $userId,
                    'guardian_code' => 'GDN-' . str_pad($i + 10000, 6, '0', STR_PAD_LEFT),
                    'address' => rand(1, 500) . ' Street ' . rand(1, 300) . ', ' . $areas[array_rand($areas)] . ', Phnom Penh',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Exception $e) {
                try {
                    DB::table('guardians')->insert([
                        'user_id' => $userId,
                        'guardian_code' => 'GDN-' . str_pad($i + 10000, 6, '0', STR_PAD_LEFT),
                        'address' => rand(1, 500) . ' Street ' . rand(1, 300) . ', ' . $areas[array_rand($areas)] . ', Phnom Penh',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } catch (\Exception $e2) {
                    continue;
                }
            }
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

        for ($i = 0; $i < $count; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];

            $students[] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => rand(0, 1) ? 'male' : 'female',
                'student_code' => 'STU-' . str_pad($i + 10000, 6, '0', STR_PAD_LEFT),
                'date_of_birth' => $now->copy()->subYears(rand(4, 18))->subDays(rand(0, 365))->toDateString(),
                'grade_level' => $grades[array_rand($grades)],
                'pickup_add' => rand(1, 500) . ' Street ' . rand(1, 300) . ', Phnom Penh',
                'dropoff_add' => rand(1, 500) . ' Street ' . rand(1, 300) . ', Phnom Penh',
                'enrollment_status' => $statuses[array_rand($statuses)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($students) >= 1000) {
                DB::table('students')->insert($students);
                $students = [];
            }
        }

        if (!empty($students)) {
            DB::table('students')->insert($students);
        }
    }

    private function seedBuses(int $count): void
    {
        $models = ['All American', 'Transit Liner', 'Vision', 'Saf-T-Liner C2', 'Micro Bird', 'Diamond', 'Patriot', 'Boss', 'Champion'];
        $manufacturers = ['Blue Bird', 'Thomas Built', 'Ford', 'IC Bus', 'Lion', 'Van Hool', 'Proterra', 'Full趟', 'Collins'];
        $statuses = ['Available', 'Available', 'Available', 'Maintenance', 'In Transit', 'Retired'];

        $buses = [];
        $now = now();

        for ($i = 0; $i < $count; $i++) {
            $buses[] = [
                'bus_number' => 'BUS-' . str_pad($i + 1000, 5, '0', STR_PAD_LEFT),
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

            if (count($buses) >= 500) {
                DB::table('buses')->insert($buses);
                $buses = [];
            }
        }

        if (!empty($buses)) {
            DB::table('buses')->insert($buses);
        }
    }

    private function seedRoutes(int $count): void
    {
        $startLocations = ['Chroy Changvar Depot', 'Touk Kork Market', 'Central Market', 'Sungkat Chbar Ampov', 'Boeung Trabek', 'Kampuchae Market', 'St 271', 'St 215', 'Tropang Thlong', 'Prek Leap'];
        $endLocations = ['ISPP Campus', 'Northbridge School', 'Golden Apple School', 'ICAN School', 'Amberwood School', 'Westbridge Academy', 'Premier International School', 'The Kids School', 'Bright Future School', 'Phnom Penh International School'];
        $areas = ['BKK1', 'Toul Kork', 'Chamkar Dor', 'Boeung Keng Kong', 'Chroy Changvar', 'Meanchey', 'Kamp Cham', 'Ta Khmao', 'Khan Sensok', 'Khan Por Senchey'];

        $routes = [];
        $now = now();

        for ($i = 0; $i < $count; $i++) {
            $routes[] = [
                'route_name' => 'Route ' . chr(65 + rand(0, 25)) . rand(1, 99) . ' - ' . $areas[array_rand($areas)] . ' Express',
                'start_location' => $startLocations[array_rand($startLocations)] . ', Phnom Penh',
                'end_location' => $endLocations[array_rand($endLocations)] . ', Phnom Penh',
                'estimated_duration' => rand(20, 120),
                'driver_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($routes) >= 500) {
                DB::table('routes')->insert($routes);
                $routes = [];
            }
        }

        if (!empty($routes)) {
            DB::table('routes')->insert($routes);
        }
    }

    private function seedDailyAttendance(int $count): void
    {
        $statuses = ['Boarded', 'Boarded', 'Boarded', 'Dropped Off', 'Absent', 'Absent', 'Late'];
        $now = now();

        $attendances = [];

        for ($i = 0; $i < $count; $i++) {
            $studentId = rand(1, DB::table('students')->count() ?: 1);
            $date = $now->copy()->subDays(rand(0, 365))->toDateString();

            $attendances[] = [
                'student_id' => $studentId,
                'date' => $date,
                'status' => $statuses[array_rand($statuses)],
                'boarding_time' => rand(0, 1) ? $now->copy()->setTime(rand(6, 8), rand(0, 59))->toDateTimeString() : null,
                'drop_off_time' => rand(0, 1) ? $now->copy()->setTime(rand(14, 17), rand(0, 59))->toDateTimeString() : null,
                'pickup_location' => rand(0, 1) ? rand(1, 500) : null,
                'recorded_by' => rand(1, DB::table('users')->where('role', 'driver')->count() ?: 1) ?: 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($attendances) >= 1000) {
                DB::table('daily_attendance')->insert($attendances);
                $attendances = [];
            }
        }

        if (!empty($attendances)) {
            DB::table('daily_attendance')->insert($attendances);
        }
    }

    private function seedInvoices(int $count): void
    {
        $statuses = ['Unpaid', 'Unpaid', 'Paid', 'Overdue', 'Pending'];
        $now = now();

        $invoices = [];

        for ($i = 0; $i < $count; $i++) {
            $guardianId = rand(1, DB::table('guardians')->count() ?: 1);
            $totalAmount = rand(50, 500) * 1000;

            $invoices[] = [
                'guardian_id' => $guardianId ?: 1,
                'invoice_date' => $now->copy()->subDays(rand(0, 180))->toDateString(),
                'due_date' => $now->copy()->addDays(rand(15, 60))->toDateString(),
                'total_amount' => $totalAmount,
                'status' => $statuses[array_rand($statuses)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($invoices) >= 1000) {
                DB::table('invoices')->insert($invoices);
                $invoices = [];
            }
        }

        if (!empty($invoices)) {
            DB::table('invoices')->insert($invoices);
        }
    }

    private function seedPayments(int $count): void
    {
        $methods = ['Cash', 'Bank Transfer', 'ABA Pay', 'ACLEDA Pay', 'Credit Card', 'Wallet'];
        $now = now();

        $payments = [];

        for ($i = 0; $i < $count; $i++) {
            $invoiceId = rand(1, DB::table('invoices')->count() ?: 1);

            $payments[] = [
                'invoice_id' => $invoiceId ?: 1,
                'payment_date' => $now->copy()->subDays(rand(0, 180))->toDateString(),
                'amount_paid' => rand(20, 200) * 1000,
                'payment_method' => $methods[array_rand($methods)],
                'transaction_reference' => 'TXN-' . strtoupper(uniqid()) . '-' . rand(1000, 9999),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($payments) >= 1000) {
                DB::table('payments')->insert($payments);
                $payments = [];
            }
        }

        if (!empty($payments)) {
            DB::table('payments')->insert($payments);
        }
    }
}
