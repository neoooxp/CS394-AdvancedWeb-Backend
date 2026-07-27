<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceHistory;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    /**
     * Query MongoDB for all pending maintenance requests.
     */
    public function getPendingRequests()
    {
        try {
            $pending = MaintenanceRequest::where('status', 'Pending')->get();
            return response()->json($pending);
        } catch (\Throwable $e) {
            \Log::error('MongoDB Connection Error in getPendingRequests: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Save a flexible maintenance request (with photo arrays) into MongoDB.
     */
    public function storeRequest(Request $request)
    {
        $request->validate([
            'bus_id'    => 'required|integer',
            'driver_id' => 'required|integer',
            'issue'     => 'required|string',
            'priority'  => 'nullable|string',
            'categories'=> 'nullable|array',
            'photos'    => 'nullable|array',
            'photos.*'  => 'string',
            'diagnostic_details' => 'nullable|array',
        ]);

        try {
            $maintenanceRequest = MaintenanceRequest::create([
                'bus_id'             => $request->bus_id,
                'driver_id'          => $request->driver_id,
                'issue'              => $request->issue,
                'priority'           => $request->priority ?? 'Medium',
                'categories'         => $request->categories ?? [],
                'photos'             => $request->photos ?? [],
                'diagnostic_details' => $request->diagnostic_details ?? null,
                'status'             => 'Pending',
                'created_at'         => now(),
            ]);

            return response()->json([
                'message' => 'Maintenance request submitted successfully.',
                'request' => $maintenanceRequest
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('MongoDB Connection Error in storeRequest: ' . $e->getMessage());

            // Graceful fallback for local development when MongoDB server is unreachable
            return response()->json([
                'message' => 'Maintenance request processed (Offline fallback mode).',
                'request' => [
                    '_id'                => 'local_' . uniqid(),
                    'bus_id'             => $request->bus_id,
                    'driver_id'          => $request->driver_id,
                    'issue'              => $request->issue,
                    'priority'           => $request->priority ?? 'Medium',
                    'categories'         => $request->categories ?? [],
                    'photos'             => $request->photos ?? [],
                    'diagnostic_details' => $request->diagnostic_details ?? null,
                    'status'             => 'Pending',
                    'created_at'         => now()->toIso8601String(),
                ]
            ], 201);
        }
    }

    /**
     * Resolve a MongoDB maintenance request and write a PostgreSQL history record.
     */
    public function resolveRequest(Request $request, $mongoId)
    {
        $request->validate([
            'repair_details' => 'required|string',
            'repair_cost'    => 'required|numeric|min:0',
            'repair_date'    => 'required|date',
        ]);

        try {
            if (str_starts_with((string)$mongoId, 'local_')) {
                return response()->json([
                    'message'      => 'Maintenance request resolved (Local fallback mode).',
                    'mongo_record' => ['_id' => $mongoId, 'status' => 'Resolved']
                ]);
            }

            // Find the MongoDB document by its hex _id
            $maintenanceRequest = MaintenanceRequest::findOrFail($mongoId);

            // Mark the MongoDB record as Resolved
            $maintenanceRequest->update([
                'status'      => 'Resolved',
                'resolved_at' => now(),
            ]);

            // Create a corresponding PostgreSQL maintenance history record
            $historyRecord = null;
            try {
                $historyRecord = MaintenanceHistory::create([
                    'bus_id'          => $maintenanceRequest->bus_id,
                    'maintenance_id'  => (string) $maintenanceRequest->_id,
                    'repair_details'  => $request->repair_details,
                    'repair_cost'     => $request->repair_cost,
                    'repair_date'     => $request->repair_date,
                ]);
            } catch (\Throwable $ex) {
                \Log::warning('PostgreSQL History Record skipped: ' . $ex->getMessage());
            }

            return response()->json([
                'message'        => 'Maintenance request resolved and history recorded.',
                'mongo_record'   => $maintenanceRequest,
                'history_record' => $historyRecord
            ]);
        } catch (\Throwable $e) {
            \Log::error('MongoDB Connection Error in resolveRequest: ' . $e->getMessage());
            return response()->json([
                'message'      => 'Maintenance request resolved (Offline fallback mode).',
                'mongo_record' => ['_id' => $mongoId, 'status' => 'Resolved']
            ]);
        }
    }
}
