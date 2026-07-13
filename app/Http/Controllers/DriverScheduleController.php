<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverSchedule;
use Illuminate\Http\Request;

class DriverScheduleController extends Controller
{
    /**
     * Retrieve all shift records for the authenticated driver.
     */
    public function getSchedule(Request $request)
    {
        $request->validate([
            'driver_id' => 'required|integer|exists:drivers,id',
        ]);

        $schedules = DriverSchedule::where('driver_id', $request->driver_id)
            ->orderBy('shift_start_time')
            ->get();

        return response()->json($schedules);
    }

    /**
     * Toggle the is_available flag on the driver's active schedule row.
     */
    public function toggleAvailability(Request $request)
    {
        $request->validate([
            'driver_id'    => 'required|integer|exists:drivers,id',
            'is_available' => 'required|in:0,1',
        ]);

        // Update the most recent schedule row for this driver
        $schedule = DriverSchedule::where('driver_id', $request->driver_id)
            ->latest()
            ->first();

        if (!$schedule) {
            return response()->json([
                'message' => 'No schedule found for this driver.'
            ], 404);
        }

        $schedule->update([
            'is_available' => $request->is_available,
        ]);

        return response()->json([
            'message'      => 'Driver availability updated.',
            'is_available' => $schedule->is_available
        ]);
    }
}
