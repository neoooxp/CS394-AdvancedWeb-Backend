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
        $pending = MaintenanceRequest::where('status', 'Pending')->get();

        return response()->json($pending);
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
            'photos'    => 'nullable|array',
            'photos.*'  => 'string',
        ]);

        $maintenanceRequest = MaintenanceRequest::create([
            'bus_id'    => $request->bus_id,
            'driver_id' => $request->driver_id,
            'issue'     => $request->issue,
            'photos'    => $request->photos ?? [],
            'status'    => 'Pending',
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Maintenance request submitted successfully.',
            'request' => $maintenanceRequest
        ], 201);
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

        // Find the MongoDB document by its hex _id
        $maintenanceRequest = MaintenanceRequest::findOrFail($mongoId);

        // Mark the MongoDB record as Resolved
        $maintenanceRequest->update([
            'status'      => 'Resolved',
            'resolved_at' => now(),
        ]);

        // Create a corresponding PostgreSQL maintenance history record
        $historyRecord = MaintenanceHistory::create([
            'bus_id'          => $maintenanceRequest->bus_id,
            'maintenance_id'  => (string) $maintenanceRequest->_id,
            'repair_details'  => $request->repair_details,
            'repair_cost'     => $request->repair_cost,
            'repair_date'     => $request->repair_date,
        ]);

        return response()->json([
            'message'        => 'Maintenance request resolved and history recorded.',
            'mongo_record'   => $maintenanceRequest,
            'history_record' => $historyRecord
        ]);
    }
}
