<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_id',
        'license_number',
        'license_expiry_date',
        'employment_status',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry_date' => 'date',
        ];
    }

    /**
     * Relationship with User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Relationship with DriverBusAssignments.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DriverBusAssignment::class, 'driver_id', 'id');
    }

    /**
     * Relationship with DriverSchedules.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(DriverSchedule::class, 'driver_id', 'id');
    }
}
