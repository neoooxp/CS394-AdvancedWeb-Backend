<?php

namespace App\Models;

// CRITICAL: Import the MongoDB Eloquent model, not the default Laravel one
use MongoDB\Laravel\Eloquent\Model; 

class MaintenanceRequest extends Model
{
    // Tell the model to use the MongoDB connection
    protected $connection = 'mongodb'; 
    protected $collection = 'maintenance_requests';

    // Guard nothing so it can accept flexible JSON data like arrays of photos
    protected $guarded = []; 

    // Cast the photos array so Laravel automatically encodes/decodes it as JSON
    protected $casts = [
        'photos' => 'array',
    ];
}
