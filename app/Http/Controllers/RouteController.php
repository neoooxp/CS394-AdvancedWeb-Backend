<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\Student;
use App\Models\StudentStop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    /**
     * List geographic paths registered in the database with optional driver_id filter.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $query = Route::query();

        if ($request->filled('student_id')) {
            $studentId = $request->query('student_id');
            $query->whereHas('stops', function ($q) use ($studentId) {
                $q->where('student_id', $studentId);
            });
        }

        if ($request->filled('driver_id')) {
            $driverId = $request->query('driver_id');
            $query->where(function ($q) use ($driverId) {
                $q->where('driver_id', $driverId)
                  ->orWhereHas('driver', fn($subQ) => $subQ->where('user_id', $driverId));
            });
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('route_name', 'ILIKE', "%{$search}%")
                  ->orWhere('start_location', 'ILIKE', "%{$search}%")
                  ->orWhere('end_location', 'ILIKE', "%{$search}%");
            });
        }

        if ($perPage === 'all' || $perPage == -1) {
            $routes = $query->with(['students', 'driver', 'buses', 'stops.student'])->get();
            return response()->json([
                'data' => $routes,
                'summary_stats' => [
                    'total_routes' => Route::count(),
                    'assigned_routes' => Route::whereNotNull('driver_id')->count(),
                ]
            ]);
        }

        $routes = $query->with(['students', 'driver', 'buses', 'stops.student'])->paginate($perPage);

        $responseArray = $routes->toArray();
        $responseArray['summary_stats'] = [
            'total_routes' => Route::count(),
            'assigned_routes' => Route::whereNotNull('driver_id')->count(),
        ];

        return response()->json($responseArray);
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
            'driver_id'          => 'nullable|integer',
        ]);

        $route = Route::create($request->only([
            'route_name', 'start_location', 'end_location', 'estimated_duration', 'driver_id'
        ]));

        return response()->json([
            'message' => 'Route created successfully.',
            'route'   => $route->load(['driver', 'buses'])
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
            'route'   => $route->load(['driver', 'students', 'buses', 'stops'])
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

        $requestedStudentIds = array_filter(array_column($request->stops, 'student_id'));
        $validIds = [];
        if (!empty($requestedStudentIds)) {
            $validIds = Student::whereIn('student_id', $requestedStudentIds)->pluck('student_id')->toArray();
        }

        DB::transaction(function () use ($request, $route, $validIds) {
            StudentStop::where('route_id', $route->route_id)->delete();

            $stopsData = array_map(function ($stop) use ($route, $validIds) {
                $studentId = $stop['student_id'] ?? null;
                if ($studentId && !in_array($studentId, $validIds)) {
                    $studentId = null;
                }

                return [
                    'route_id'     => $route->route_id,
                    'student_id'   => $studentId,
                    'stop_address' => $stop['stop_address'],
                    'stop_order'   => $stop['stop_order'],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }, $request->stops);

            StudentStop::insert($stopsData);
        });

        return response()->json([
            'message' => 'Route stops synchronized successfully.',
            'route'   => $route->load(['students', 'stops', 'stops.student'])
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
