<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\Student;
use App\Models\StudentGuardian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentGuardianController extends Controller
{
    /**
     * Return students with eager-loaded guardian information and optional query filters.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $query = Student::query();

        // Filter by Guardian ID or User ID
        if ($request->filled('guardian_id')) {
            $guardianId = $request->query('guardian_id');
            $query->whereHas('guardians', function ($q) use ($guardianId) {
                $q->where('guardians.guardian_id', $guardianId)
                  ->orWhere('guardians.user_id', $guardianId);
            });
        }

        // Filter by Grade Level
        if ($request->filled('grade') && $request->query('grade') !== 'All') {
            $query->where('grade_level', $request->query('grade'));
        }

        // Filter by Route ID or Route Name
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

        // Search Filter (First Name, Last Name, Student Code)
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ILIKE', "%{$search}%")
                  ->orWhere('last_name', 'ILIKE', "%{$search}%")
                  ->orWhere('student_code', 'ILIKE', "%{$search}%")
                  ->orWhere(DB::raw("first_name || ' ' || last_name"), 'ILIKE', "%{$search}%");
            });
        }

        $students = $query->with([
            'guardians.user',
            'medicalRecord',
            'stops',
            'feeStructures'
        ])->paginate($perPage);

        // Global System Statistics (independent of active filter/page)
        $totalStudents = Student::count();
        $currentlyEnrolled = Student::where(function($q) {
            $q->whereNull('enrollment_status')
              ->orWhereRaw('LOWER(enrollment_status) = ?', ['active']);
        })->count();
        $suspendedAccounts = Student::whereRaw('LOWER(enrollment_status) IN (?, ?)', ['suspended', 'inactive'])->count();
        $transportUsers = Student::has('stops')->count();

        $responseArray = $students->toArray();
        $responseArray['summary_stats'] = [
            'total_students' => $totalStudents,
            'currently_enrolled' => $currentlyEnrolled,
            'suspended_accounts' => $suspendedAccounts,
            'transport_users' => $transportUsers,
        ];

        return response()->json($responseArray);
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

        return response()->json([
            'message'    => 'Guardian assigned to student successfully.',
            'assignment' => $assignment
        ], 201);
    }

    /**
     * Remove a student record and all associated stop/guardian relationships.
     */
    public function destroy($id)
    {
        $student = Student::findOrFail($id);

        DB::transaction(function () use ($student) {
            // Cascade delete stop associations
            \App\Models\StudentStop::where('student_id', $student->student_id)->delete();
            // Delete guardian links
            StudentGuardian::where('student_id', $student->student_id)->delete();
            // Delete the student record
            $student->delete();
        });

        return response()->json([
            'message' => 'Student deleted successfully.',
        ]);
    }
}
