<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use App\Models\BusDocument;
use Illuminate\Http\Request;

class BusController extends Controller
{
    /**
     * Display all active bus assets with capacity, odometer, and deployment data.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $query = Bus::with(['documents', 'routes.driver', 'assignments.driver.user']);

        if ($request->filled('status') && strtolower($request->query('status')) !== 'all') {
            $query->whereRaw('LOWER(availability_status) = ?', [strtolower($request->query('status'))]);
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('bus_number', 'ILIKE', "%{$search}%")
                  ->orWhere('plate_number', 'ILIKE', "%{$search}%")
                  ->orWhere('model', 'ILIKE', "%{$search}%")
                  ->orWhere('manufacturer', 'ILIKE', "%{$search}%");
            });
        }

        if ($perPage === 'all' || $perPage == -1) {
            $buses = $query->get();
            return response()->json([
                'data' => $buses,
                'summary_stats' => [
                    'total_buses' => Bus::count(),
                    'active_buses' => Bus::whereRaw('LOWER(availability_status) = ?', ['active'])->count(),
                    'maintenance_buses' => Bus::whereRaw('LOWER(availability_status) IN (?, ?)', ['in_service', 'maintenance'])->count(),
                ]
            ]);
        }

        $buses = $query->paginate($perPage);

        $responseArray = $buses->toArray();
        $responseArray['summary_stats'] = [
            'total_buses' => Bus::count(),
            'active_buses' => Bus::whereRaw('LOWER(availability_status) = ?', ['active'])->count(),
            'maintenance_buses' => Bus::whereRaw('LOWER(availability_status) IN (?, ?)', ['in_service', 'maintenance'])->count(),
        ];

        return response()->json($responseArray);
    }

    /**
     * Append a new physical vehicle entry to the buses table.
     */
    public function store(Request $request)
    {
        $request->validate([
            'bus_number'          => 'required|string|unique:buses,bus_number',
            'plate_number'        => 'required|string|unique:buses,plate_number',
            'capacity'            => 'required|integer|min:1',
            'model'               => 'nullable|string',
            'manufacturer'        => 'nullable|string',
            'year'                => 'nullable|integer|min:1900|max:2100',
            'mileage'             => 'nullable|integer|min:0',
            'availability_status' => 'nullable|string',
        ]);

        $bus = Bus::create($request->only([
            'bus_number', 'plate_number', 'capacity', 'model',
            'manufacturer', 'year', 'mileage', 'availability_status'
        ]));

        return response()->json([
            'message' => 'Bus added successfully.',
            'bus'     => $bus
        ], 201);
    }

    /**
     * Update mutable operational properties of a bus asset.
     */
    public function update(Request $request, $busId)
    {
        $bus = Bus::findOrFail($busId);

        $request->validate([
            'capacity'            => 'nullable|integer|min:1',
            'mileage'             => 'nullable|integer|min:0',
            'availability_status' => 'nullable|string',
            'model'               => 'nullable|string',
            'manufacturer'        => 'nullable|string',
            'year'                => 'nullable|integer|min:1900|max:2100',
        ]);

        $bus->update($request->only([
            'capacity', 'mileage', 'availability_status',
            'model', 'manufacturer', 'year',
        ]));

        return response()->json([
            'message' => 'Bus updated successfully.',
            'bus'     => $bus->fresh()->load('documents'),
        ]);
    }

    /**
     * Map time-sensitive operational document criteria to a bus.
     */
    public function storeDocument(Request $request, $busId)
    {
        $bus = Bus::findOrFail($busId);

        $request->validate([
            'document_type' => 'required|string',
            'issue_date'    => 'required|date',
            'expiry_date'   => 'required|date|after:issue_date',
        ]);

        $document = BusDocument::create([
            'bus_id'        => $bus->bus_id,
            'document_type' => $request->document_type,
            'issue_date'    => $request->issue_date,
            'expiry_date'   => $request->expiry_date,
        ]);

        return response()->json([
            'message'  => 'Bus document stored successfully.',
            'document' => $document
        ], 201);
    }
}
