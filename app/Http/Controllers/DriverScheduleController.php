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
        $perPage = $request->query('per_page', 15);
        $driverId = $request->query('driver_id') ?? $request->user()?->user_id;

        if (!$driverId) {
            return response()->json(['data' => [], 'total' => 0, 'per_page' => $perPage, 'current_page' => 1]);
        }

        // Check if driver ID matches drivers.id or drivers.user_id
        $driver = Driver::where('id', $driverId)
            ->orWhere('user_id', $driverId)
            ->first();

        $targetDriverId = $driver ? $driver->id : $driverId;

        $schedules = DriverSchedule::where('driver_id', $targetDriverId)
            ->orderBy('shift_start_time')
            ->paginate($perPage);

        return response()->json($schedules);
    }

    /**
     * Toggle the is_available flag on the driver's active schedule row.
     */
    public function toggleAvailability(Request $request)
    {
        $request->validate([
            'driver_id'    => 'nullable|integer',
            'is_available' => 'required',
        ]);

        $driverId = $request->driver_id ?? $request->user()?->user_id;

        $driver = Driver::where('id', $driverId)
            ->orWhere('user_id', $driverId)
            ->first();

        $targetDriverId = $driver ? $driver->id : $driverId;

        // Update the most recent schedule row for this driver
        $schedule = DriverSchedule::where('driver_id', $targetDriverId)
            ->latest()
            ->first();

        if (!$schedule) {
            return response()->json([
                'message' => 'No schedule found for this driver.',
                'is_available' => (bool)$request->is_available
            ]);
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

