<?php

namespace App\Http\Controllers;

use App\Models\BusRoute;
use App\Models\DriverBusAssignment;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
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
