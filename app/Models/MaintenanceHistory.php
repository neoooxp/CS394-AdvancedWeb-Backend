<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\HybridRelations;

class MaintenanceHistory extends Model
{
    use HasFactory, HybridRelations;

    protected $table = 'maintenance_history';
    protected $primaryKey = 'repair_id';

    protected $fillable = [
        'bus_id',
        'maintenance_id',
        'repair_details',
        'repair_cost',
        'repair_date',
    ];

    protected function casts(): array
    {
        return [
            'repair_date' => 'date',
            'repair_cost' => 'decimal:2',
        ];
    }

    /**
     * Relationship with Bus.
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'bus_id', 'bus_id');
    }

    /**
     * Hybrid Relationship with MaintenanceRequest (MongoDB).
     */
    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_id');
    }
}
