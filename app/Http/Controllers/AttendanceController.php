<?php

namespace App\Http\Controllers;

use App\Models\AttendanceReport;
use App\Models\DailyAttendance;
use App\Models\DriverBusAssignment;
use App\Models\StudentStop;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    /**
     * Return a sequential manifest of students on a route, sorted by stop_order.
     */
    public function getRouteManifest(Request $request, $routeId)
    {
        $perPage = $request->query('per_page', 15);
        $stops = StudentStop::with('student')
            ->where('route_id', $routeId)
            ->orderBy('stop_order')
            ->paginate($perPage);

        return response()->json($stops);
    }

    /**
     * Create or update an attendance entry for a student.
     * Automatically timestamps boarding and drop-off events.
     */
    public function markAttendance(Request $request)
    {
        $request->validate([
            'student_id'      => 'required|integer|exists:students,student_id',
            'date'            => 'required|date',
            'status'          => 'required|in:Boarded,Dropped Off,Absent',
            'pickup_location' => 'nullable|numeric',
            'recorded_by'     => 'required|integer|exists:users,user_id',
        ]);

        $timestamps = [];
        if ($request->status === 'Boarded') {
            $timestamps['boarding_time'] = now();
        } elseif ($request->status === 'Dropped Off') {
            $timestamps['drop_off_time'] = now();
        }

        $attendance = DailyAttendance::updateOrCreate(
            [
                'student_id' => $request->student_id,
                'date'       => $request->date,
            ],
            array_merge([
                'status'          => $request->status,
                'pickup_location' => $request->pickup_location,
                'recorded_by'     => $request->recorded_by,
            ], $timestamps)
        );

        return response()->json([
            'message'    => 'Attendance recorded successfully.',
            'attendance' => $attendance
        ]);
    }

    /**
     * Query today's attendance for a student.
     * If the student is Boarded, return the active driver's profile.
     */
    public function getChildStatus($studentId)
    {
        $today = Carbon::today()->toDateString();

        $attendance = DailyAttendance::where('student_id', $studentId)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json([
                'status'  => 'No record',
                'message' => 'No attendance record found for today.'
            ]);
        }

        $response = [
            'status'     => $attendance->status,
            'attendance' => $attendance,
            'driver'     => null,
        ];

        // If the student is currently on the bus, retrieve the active driver profile
        if ($attendance->status === 'Boarded') {
            $driverAssignment = DriverBusAssignment::with(['driver.user'])
                ->where('assigned_date', $today)
                ->where('status', 'Active')
                ->first();

            if ($driverAssignment) {
                $response['driver'] = $driverAssignment->driver;
            }
        }

        return response()->json($response);
    }

    /**
     * Aggregate attendance data for a route and save a summary report.
     * Also marks all "Boarded" students as "Dropped Off" so the
     * guardian portal sees the route as completed.
     */
    public function generateReport($routeId)
    {
        $today = Carbon::today()->toDateString();
        $now = now();

        $studentIds = StudentStop::where('route_id', $routeId)
            ->pluck('student_id');

        // Single fetch of today's attendance for all students on this route
        $records = DailyAttendance::where('date', $today)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        // Update Boarded → Dropped Off
        $boardedIds = $records->where('status', 'Boarded')->keys();
        if ($boardedIds->isNotEmpty()) {
            DailyAttendance::where('date', $today)
                ->whereIn('student_id', $boardedIds)
                ->update(['status' => 'Dropped Off', 'drop_off_time' => $now]);
        }

        // Bulk insert records for students with no attendance today
        $missingIds = $studentIds->diff($records->keys());
        if ($missingIds->isNotEmpty()) {
            $recordedBy = auth()->user()->user_id ?? 1;
            $insertData = $missingIds->map(fn($id) => [
                'student_id'    => $id,
                'date'          => $today,
                'status'        => 'Dropped Off',
                'drop_off_time' => $now,
                'recorded_by'   => $recordedBy,
                'created_at'    => $now,
                'updated_at'    => $now,
            ])->values()->toArray();

            DailyAttendance::insert($insertData);
        }

        // Recalculate totals (merged: in-memory updated + new inserts)
        $totalPresent = $studentIds->count() - $records->where('status', 'Absent')->count();
        $totalAbsent  = $records->where('status', 'Absent')->count();

        $report = AttendanceReport::create([
            'route_id'      => $routeId,
            'generated_at'  => $now,
            'total_present' => $totalPresent,
            'total_absent'  => $totalAbsent,
            'file_path'     => 'reports/route_' . $routeId . '_' . $today . '.json',
        ]);

        return response()->json([
            'message' => 'Route completed and attendance finalized successfully.',
            'report'  => $report
        ], 201);
    }
}
