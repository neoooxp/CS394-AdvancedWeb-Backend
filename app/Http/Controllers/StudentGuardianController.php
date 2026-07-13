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
     * Return all students with eager-loaded guardian information.
     */
    public function index()
    {
        $students = Student::with([
            'guardians.user',
            'medicalRecord'
        ])->get();

        return response()->json($students);
    }

    /**
     * Create a new student and their medical record in a single transaction.
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name'        => 'required|string',
            'last_name'         => 'required|string',
            'gender'            => 'nullable|string',
            'student_code'      => 'required|string|unique:students,student_code',
            'date_of_birth'     => 'required|date',
            'grade_level'       => 'nullable|string',
            'pickup_add'        => 'nullable|string',
            'dropoff_add'       => 'nullable|string',
            'medical_conditions' => 'nullable|string',
            'special_needs'     => 'nullable|string',
            'emergency_notes'   => 'nullable|string',
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
        });

        return response()->json([
            'message' => 'Student created successfully.',
            'student' => $student->load('medicalRecord')
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
        });

        return response()->json([
            'message' => 'Student updated successfully.',
            'student' => $student->fresh()->load('medicalRecord')
        ]);
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
}
