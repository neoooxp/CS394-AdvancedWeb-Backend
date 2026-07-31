<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\Student;
use App\Models\StudentGuardian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StudentGuardianController extends Controller
{
    /**
     * Return students with eager-loaded guardian information and optional query filters.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $version = Cache::remember('students:version_v2', 86400, fn() => 2);
        $cacheKey = 'students:list:v' . $version . ':' . md5(json_encode($request->query()));

        $data = Cache::remember($cacheKey, 300, function () use ($request, $perPage) {
            $query = Student::query();

            if ($request->filled('guardian_id')) {
                $guardianId = $request->query('guardian_id');
                $query->whereHas('guardians', function ($q) use ($guardianId) {
                    $q->where('guardians.guardian_id', $guardianId)
                      ->orWhere('guardians.user_id', $guardianId);
                });
            }

            if ($request->filled('grade') && $request->query('grade') !== 'All') {
                $query->where('grade_level', $request->query('grade'));
            }

            if ($request->filled('status') && strtolower($request->query('status')) !== 'all') {
                $statusVal = strtolower($request->query('status'));
                if ($statusVal === 'suspended') {
                    $query->whereRaw('LOWER(enrollment_status) IN (?, ?)', ['suspended', 'inactive']);
                } elseif ($statusVal === 'enrolled' || $statusVal === 'active') {
                    $query->whereRaw('LOWER(enrollment_status) NOT IN (?, ?)', ['suspended', 'inactive']);
                } else {
                    $query->where('enrollment_status', $request->query('status'));
                }
            }

            if ($request->filled('route_id') && $request->query('route_id') !== 'All') {
                $routeVal = $request->query('route_id');
                $query->whereHas('stops', function ($q) use ($routeVal) {
                    if (is_numeric($routeVal)) {
                        $q->where('route_id', $routeVal);
                    } else {
                        $q->whereHas('route', function ($rq) use ($routeVal) {
                            $rq->where('route_name', $routeVal);
                        });
                    }
                });
            }

            if ($request->filled('search')) {
                $search = $request->query('search');
                $isPg = DB::connection()->getDriverName() === 'pgsql';
                $likeOp = $isPg ? 'ILIKE' : 'LIKE';
                $concat = $isPg ? "first_name || ' ' || last_name" : "CONCAT(first_name, ' ', last_name)";

                $query->where(function ($q) use ($search, $likeOp, $concat) {
                    $q->where('first_name', $likeOp, "%{$search}%")
                      ->orWhere('last_name', $likeOp, "%{$search}%")
                      ->orWhere('student_code', $likeOp, "%{$search}%")
                      ->orWhere(DB::raw($concat), $likeOp, "%{$search}%")
                      ->orWhereHas('guardians', function ($gq) use ($search, $likeOp, $concat) {
                          $gq->whereHas('user', function ($uq) use ($search, $likeOp, $concat) {
                              $uq->where('first_name', $likeOp, "%{$search}%")
                                 ->orWhere('last_name', $likeOp, "%{$search}%")
                                 ->orWhere(DB::raw($concat), $likeOp, "%{$search}%");
                          });
                      });
                });
            }

            $summaryStats = Cache::remember('students:summary', 300, function () {
                return [
                    'total_students' => Student::count(),
                    'currently_enrolled' => Student::where(function($q) {
                        $q->whereNull('enrollment_status')
                          ->orWhereRaw('LOWER(enrollment_status) = ?', ['active']);
                    })->count(),
                    'suspended_accounts' => Student::whereRaw('LOWER(enrollment_status) IN (?, ?)', ['suspended', 'inactive'])->count(),
                    'transport_users' => Student::has('stops')->count(),
                ];
            });

            if ($perPage === 'all' || $perPage == -1) {
                $students = $query->with([
                    'guardians.user', 'medicalRecord', 'stops', 'feeStructures'
                ])->get();

                return [
                    'data' => $students,
                    'summary_stats' => $summaryStats,
                ];
            }

            $students = $query->with([
                'guardians.user', 'medicalRecord', 'stops', 'feeStructures'
            ])->paginate($perPage);

            $responseArray = $students->toArray();
            $responseArray['summary_stats'] = $summaryStats;

            return $responseArray;
        });

        return response()->json($data);
    }

    /**
     * Create a new student and their medical record in a single transaction.
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name'         => 'required|string',
            'last_name'          => 'required|string',
            'gender'             => 'nullable|string',
            'student_code'       => 'required|string|unique:students,student_code',
            'date_of_birth'      => 'required|date',
            'grade_level'        => 'nullable|string',
            'pickup_add'         => 'nullable|string',
            'dropoff_add'        => 'nullable|string',
            'guardian_id'        => 'nullable|integer',
            'guardian_user_id'   => 'nullable|integer',
            'guardian_name'      => 'nullable|string',
            'medical_conditions' => 'nullable|string',
            'special_needs'      => 'nullable|string',
            'emergency_notes'    => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, &$student) {
            $student = Student::create([
                'first_name'        => $request->first_name,
                'last_name'         => $request->last_name,
                'gender'            => $request->gender,
                'student_code'      => $request->student_code,
                'date_of_birth'     => $request->date_of_birth,
                'grade_level'       => $request->grade_level,
                'pickup_add'        => $request->pickup_add,
                'dropoff_add'       => $request->dropoff_add,
                'enrollment_status' => 'Active',
            ]);

            MedicalRecord::create([
                'student_id'         => $student->student_id,
                'medical_conditions' => $request->medical_conditions,
                'special_needs'      => $request->special_needs,
                'emergency_notes'    => $request->emergency_notes,
            ]);

            $this->syncGuardianLink($student, $request);
        });

        $this->invalidateStudentCache();

        return response()->json([
            'message' => 'Student created successfully.',
            'student' => $student->load(['medicalRecord', 'guardians.user'])
        ], 201);
    }

    /**
     * Update student details or their medical record.
     */
    public function update(Request $request, $id)
    {
        $student = Student::findOrFail($id);

        $request->validate([
            'first_name'         => 'sometimes|string',
            'last_name'          => 'sometimes|string',
            'gender'             => 'nullable|string',
            'grade_level'        => 'nullable|string',
            'pickup_add'         => 'nullable|string',
            'dropoff_add'        => 'nullable|string',
            'enrollment_status'  => 'nullable|string',
            'guardian_id'        => 'nullable|integer',
            'guardian_user_id'   => 'nullable|integer',
            'guardian_name'      => 'nullable|string',
            'medical_conditions' => 'nullable|string',
            'special_needs'      => 'nullable|string',
            'emergency_notes'    => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $student) {
            $student->update($request->only([
                'first_name', 'last_name', 'gender', 'grade_level',
                'pickup_add', 'dropoff_add', 'enrollment_status',
            ]));

            if ($request->hasAny(['medical_conditions', 'special_needs', 'emergency_notes'])) {
                $student->medicalRecord()->updateOrCreate(
                    ['student_id' => $student->student_id],
                    $request->only(['medical_conditions', 'special_needs', 'emergency_notes'])
                );
            }

            $this->syncGuardianLink($student, $request);
        });

        $this->invalidateStudentCache();

        return response()->json([
            'message' => 'Student updated successfully.',
            'student' => $student->fresh()->load(['medicalRecord', 'guardians.user'])
        ]);
    }

    /**
     * Private helper to link guardian to student.
     */
    private function syncGuardianLink(Student $student, Request $request)
    {
        $guardianId = $request->input('guardian_id');

        if (!$guardianId && $request->filled('guardian_user_id')) {
            $user = \App\Models\User::find($request->guardian_user_id);
            if ($user) {
                $guardian = \App\Models\Guardian::firstOrCreate(
                    ['user_id' => $user->user_id],
                    ['guardian_code' => 'G-' . sprintf('%04d', $user->user_id), 'address' => 'Phnom Penh']
                );
                $guardianId = $guardian->guardian_id;
            }
        }

        if (!$guardianId && $request->filled('guardian_name')) {
            $name = trim($request->guardian_name);
            $user = \App\Models\User::where('role', 'guardian')
                ->where(function($q) use ($name) {
                    $q->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'ILIKE', "%{$name}%")
                      ->orWhere('username', 'ILIKE', "%{$name}%");
                })->first();

            if ($user) {
                $guardian = \App\Models\Guardian::firstOrCreate(
                    ['user_id' => $user->user_id],
                    ['guardian_code' => 'G-' . sprintf('%04d', $user->user_id), 'address' => 'Phnom Penh']
                );
                $guardianId = $guardian->guardian_id;
            }
        }

        if ($guardianId) {
            StudentGuardian::updateOrCreate(
                ['student_id' => $student->student_id],
                ['guardian_id' => $guardianId, 'relationship_type' => 'Parent']
            );
        }
    }

    /**
     * Write a guardian-student mapping to the student_guardians junction table.
     */
    public function assignGuardian(Request $request)
    {
        $request->validate([
            'student_id'        => 'required|integer|exists:students,student_id',
            'guardian_id'       => 'required|integer|exists:guardians,guardian_id',
            'relationship_type' => 'required|string',
        ]);

        $assignment = StudentGuardian::updateOrCreate(
            [
                'student_id'  => $request->student_id,
                'guardian_id' => $request->guardian_id,
            ],
            [
                'relationship_type' => $request->relationship_type,
            ]
        );

        $this->invalidateStudentCache();

        return response()->json([
            'message'    => 'Guardian assigned to student successfully.',
            'assignment' => $assignment
        ], 201);
    }

    /**
     * Toggle the enrollment/suspension status of a student.
     */
    public function toggleStatus($id)
    {
        $student = Student::findOrFail($id);
        $currentStatus = strtolower($student->enrollment_status ?? 'enrolled');
        
        $newStatus = ($currentStatus === 'suspended') ? 'Enrolled' : 'Suspended';
        $student->update(['enrollment_status' => $newStatus]);

        $this->invalidateStudentCache();
        Cache::increment('routes:version');

        return response()->json([
            'message'           => "Student status updated to '{$newStatus}'.",
            'enrollment_status' => $newStatus,
            'student'           => $student
        ]);
    }

    /**
     * Remove a student record and all associated stop/guardian relationships.
     */
    public function destroy($id)
    {
        $student = Student::findOrFail($id);

        DB::transaction(function () use ($student) {
            \App\Models\StudentStop::where('student_id', $student->student_id)->delete();
            StudentGuardian::where('student_id', $student->student_id)->delete();
            $student->delete();
        });

        $this->invalidateStudentCache();

        return response()->json([
            'message' => 'Student deleted successfully.',
        ]);
    }

    private function invalidateStudentCache()
    {
        Cache::forget('students:summary');
        Cache::increment('students:version');
    }
}
