<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        $cacheKey = "driver:schedule:{$driverId}:page:{$perPage}";

        $data = Cache::remember($cacheKey, 120, function () use ($driverId, $perPage) {
            $driver = Driver::where('id', $driverId)
                ->orWhere('user_id', $driverId)
                ->first();

            $targetDriverId = $driver ? $driver->id : $driverId;

            return DriverSchedule::where('driver_id', $targetDriverId)
                ->orderBy('shift_start_time')
                ->paginate($perPage);
        });

        return response()->json($data);
    }

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

        Cache::put("driver:availability:{$targetDriverId}", $request->is_available, 300);

        return response()->json([
            'message'      => 'Driver availability updated.',
            'is_available' => $schedule->is_available
        ]);
    }
}

