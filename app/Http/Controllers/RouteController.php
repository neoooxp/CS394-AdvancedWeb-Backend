<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\StudentStop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    /**
     * List all geographic paths registered in the database.
     */
    public function index()
    {
        $routes = Route::with(['students', 'driver', 'buses'])->get();

        return response()->json($routes);
    }

    /**
     * Create a new route entry in the routes table.
     */
    public function store(Request $request)
    {
        $request->validate([
            'route_name'         => 'required|string',
            'start_location'     => 'required|string',
            'end_location'       => 'required|string',
            'estimated_duration' => 'required|integer|min:1',
        ]);

        $route = Route::create($request->only([
            'route_name', 'start_location', 'end_location', 'estimated_duration'
        ]));

        return response()->json([
            'message' => 'Route created successfully.',
            'route'   => $route
        ], 201);
    }

    /**
     * Update route details (e.g., rename route).
     */
    public function update(Request $request, $routeId)
    {
        $route = Route::findOrFail($routeId);

        $request->validate([
            'route_name'         => 'sometimes|string',
            'start_location'     => 'sometimes|string',
            'end_location'       => 'sometimes|string',
            'estimated_duration' => 'sometimes|integer|min:1',
            'driver_id'          => 'sometimes|nullable|integer',
        ]);

        $route->update($request->only([
            'route_name', 'start_location', 'end_location', 'estimated_duration', 'driver_id'
        ]));

        return response()->json([
            'message' => 'Route updated successfully.',
            'route'   => $route
        ]);
    }

    /**
     * Synchronize student stop sequences for a route.
     * Replaces all existing stops for the route and re-inserts them with correct stop_order.
     */
    public function manageStops(Request $request, $routeId)
    {
        $route = Route::findOrFail($routeId);

        $request->validate([
            'stops'               => 'required|array|min:1',
            'stops.*.student_id'  => 'nullable|integer',
            'stops.*.stop_address' => 'required|string',
            'stops.*.pickup_time' => 'nullable|string',
            'stops.*.stop_order'  => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request, $route) {
            // Remove all existing stops for this route
            StudentStop::where('route_id', $route->route_id)->delete();

            // Re-insert stops with explicit stop_order
            foreach ($request->stops as $stop) {
                StudentStop::create([
                    'route_id'    => $route->route_id,
                    'student_id'  => $stop['student_id'] ?? null,
                    'stop_address' => $stop['stop_address'],
                    'stop_order'  => $stop['stop_order'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Route stops synchronized successfully.',
            'route'   => $route->load('students')
        ]);
    }

    /**
     * Delete a route and remove all associated stops.
     */
    public function destroy($routeId)
    {
        $route = Route::findOrFail($routeId);

        DB::transaction(function () use ($route) {
            // Delete associated student stops
            StudentStop::where('route_id', $route->route_id)->delete();
            // Delete the route
            $route->delete();
        });

        return response()->json([
            'message' => 'Route deleted successfully.',
            'route_id' => $routeId
        ]);
    }
}
