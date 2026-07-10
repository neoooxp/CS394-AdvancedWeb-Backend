<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasFactory;

    protected $primaryKey = 'route_id';

    protected $fillable = [
        'route_name',
        'start_location',
        'end_location',
        'estimated_duration',
    ];

    /**
     * Relationship with Students (via stops).
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'students_stop', 'route_id', 'student_id')
            ->withPivot('student_stop_id', 'stop_address', 'stop_order')
            ->withTimestamps();
    }

    /**
     * Relationship with Buses.
     */
    public function buses(): BelongsToMany
    {
        return $this->belongsToMany(Bus::class, 'bus_routes', 'route_id', 'bus_id')
            ->withPivot('bus_route_id')
            ->withTimestamps();
    }

    /**
     * Relationship with AttendanceReports.
     */
    public function attendanceReports(): HasMany
    {
        return $this->hasMany(AttendanceReport::class, 'route_id', 'route_id');
    }
}
