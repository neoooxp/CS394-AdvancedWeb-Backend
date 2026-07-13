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
        $buses = Bus::with('documents')->get();

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
            'depot_location'      => 'nullable|string',
        ]);

        $bus = Bus::create($request->only([
            'bus_number', 'plate_number', 'capacity', 'model',
            'manufacturer', 'year', 'mileage', 'availability_status', 'depot_location'
        ]));

        return response()->json([
            'message' => 'Bus added successfully.',
            'bus'     => $bus
        ], 201);
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
