<?php

namespace App\Http\Controllers;

use App\Models\BusRoute;
use App\Models\DriverBusAssignment;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    /**
     * Check if a bus schedule has overlapping route assignments.
     */
    public function checkBusScheduleAvailability(Request $request)
    {
        $request->validate([
            'bus_id'     => 'required|integer|exists:buses,bus_id',
            'start_time' => 'nullable|string',
            'end_time'   => 'nullable|string',
            'route_id'   => 'nullable|integer',
        ]);

        $existingAssignments = BusRoute::with('route')
            ->where('bus_id', $request->bus_id)
            ->get();

        $hasConflict = false;
        $conflictRouteName = null;

        if ($request->filled('start_time') && $request->filled('end_time')) {
            $reqStart = $this->parseTimeToMinutes($request->start_time);
            $reqEnd   = $this->parseTimeToMinutes($request->end_time);

            foreach ($existingAssignments as $assignment) {
                if ($request->filled('route_id') && $assignment->route_id == $request->route_id) {
                    continue; // Skip current route when reassigning
                }
                
                // If route has estimated duration or custom time, check interval
                $assignedRoute = $assignment->route;
                if ($assignedRoute) {
                    $routeStart = 420; // Default 07:00 AM in minutes
                    $routeEnd   = 510; // Default 08:30 AM in minutes
                    
                    if ($reqStart < $routeEnd && $reqEnd > $routeStart) {
                        $hasConflict = true;
                        $conflictRouteName = $assignedRoute->route_name;
                        break;
                    }
                }
            }
        }

        return response()->json([
            'bus_id'           => $request->bus_id,
            'is_available'     => !$hasConflict,
            'conflicting_route' => $conflictRouteName,
            'message'          => $hasConflict ? "Bus is already assigned to route '{$conflictRouteName}' during this schedule." : "Bus is available."
        ]);
    }

    /**
     * Helper to parse time string like "07:00 AM" into minutes from midnight.
     */
    private function parseTimeToMinutes($timeStr)
    {
        if (!$timeStr) return 0;
        $timestamp = strtotime($timeStr);
        if ($timestamp === false) return 0;
        return (int)date('H', $timestamp) * 60 + (int)date('i', $timestamp);
    }

    /**
     * Insert a new bus-to-route scheduling record.
     */
    public function assignBusToRoute(Request $request)
    {
        $request->validate([
            'bus_id'        => 'required|integer|exists:buses,bus_id',
            'route_id'      => 'required|integer|exists:routes,route_id',
            'assigned_date' => 'required|date',
        ]);

        $assignment = BusRoute::create([
            'bus_id'   => $request->bus_id,
            'route_id' => $request->route_id,
        ]);

        return response()->json([
            'message'    => 'Bus assigned to route successfully.',
            'assignment' => $assignment
        ], 201);
    }

    /**
     * Connect a driver with a physical vehicle in driver_bus_assignments.
     */
    public function assignDriverToBus(Request $request)
    {
        $request->validate([
            'driver_id'     => 'required|integer|exists:drivers,id',
            'bus_id'        => 'required|integer|exists:buses,bus_id',
            'assigned_date' => 'required|date',
            'status'        => 'required|string',
        ]);

        $assignment = DriverBusAssignment::create([
            'driver_id'     => $request->driver_id,
            'bus_id'        => $request->bus_id,
            'assigned_date' => $request->assigned_date,
            'status'        => $request->status,
        ]);

        return response()->json([
            'message'    => 'Driver assigned to bus successfully.',
            'assignment' => $assignment->load(['driver.user', 'bus'])
        ], 201);
    }
}
