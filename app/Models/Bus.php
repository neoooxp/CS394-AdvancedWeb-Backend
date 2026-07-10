<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bus extends Model
{
    use HasFactory;

    protected $primaryKey = 'bus_id';

    protected $fillable = [
        'bus_number',
        'plate_number',
        'capacity',
        'model',
        'manufacturer',
        'year',
        'mileage',
        'availability_status',
        'depot_location',
    ];

    /**
     * Relationship with BusDocuments.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(BusDocument::class, 'bus_id', 'bus_id');
    }

    /**
     * Relationship with MaintenanceHistories.
     */
    public function maintenanceHistories(): HasMany
    {
        return $this->hasMany(MaintenanceHistory::class, 'bus_id', 'bus_id');
    }

    /**
     * Relationship with Routes.
     */
    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'bus_routes', 'bus_id', 'route_id')
            ->withPivot('bus_route_id')
            ->withTimestamps();
    }

    /**
     * Relationship with DriverBusAssignments.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DriverBusAssignment::class, 'bus_id', 'bus_id');
    }
}
