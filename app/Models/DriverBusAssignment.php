<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverBusAssignment extends Model
{
    use HasFactory;

    protected $table = 'driver_bus_assignments';
    protected $primaryKey = 'assignment_id';

    protected $fillable = [
        'driver_id',
        'bus_id',
        'assigned_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
        ];
    }

    /**
     * Relationship with Driver.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }

    /**
     * Relationship with Bus.
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'bus_id', 'bus_id');
    }
}
