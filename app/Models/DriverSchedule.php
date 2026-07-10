<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverSchedule extends Model
{
    use HasFactory;

    protected $table = 'driver_schedules';

    protected $fillable = [
        'driver_id',
        'shift_start_time',
        'shift_end_time',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'shift_start_time' => 'datetime',
            'shift_end_time' => 'datetime',
            'is_available' => 'integer',
        ];
    }

    /**
     * Relationship with Driver.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }
}
