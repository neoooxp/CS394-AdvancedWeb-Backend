<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAttendance extends Model
{
    use HasFactory;

    protected $table = 'daily_attendance';
    protected $primaryKey = 'attendance_id';

    protected $fillable = [
        'student_id',
        'date',
        'status',
        'boarding_time',
        'drop_off_time',
        'pickup_location',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'boarding_time' => 'datetime',
            'drop_off_time' => 'datetime',
            'pickup_location' => 'float',
        ];
    }

    /**
     * Relationship with Student.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * Relationship with User who recorded it.
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by', 'user_id');
    }
}
