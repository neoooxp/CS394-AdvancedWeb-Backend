<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusRoute extends Model
{
    use HasFactory;

    protected $table = 'bus_routes';
    protected $primaryKey = 'bus_route_id';

    const UPDATED_AT = null;

    protected $fillable = [
        'bus_id',
        'route_id',
    ];

    /**
     * Relationship with Bus.
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'bus_id', 'bus_id');
    }

    /**
     * Relationship with Route.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id', 'route_id');
    }
}
