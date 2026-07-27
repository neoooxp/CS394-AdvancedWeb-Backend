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
    public function index()
    {
        $buses = Bus::with(['documents', 'routes.driver', 'assignments.driver.user'])->get();

        return response()->json($buses);
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
