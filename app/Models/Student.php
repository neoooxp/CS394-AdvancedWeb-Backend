<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $primaryKey = 'student_id';

    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'student_code',
        'date_of_birth',
        'grade_level',
        'pickup_add',
        'dropoff_add',
        'enrollment_status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Relationship with Guardians.
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians', 'student_id', 'guardian_id')
            ->withPivot('relationship_type')
            ->withTimestamps();
    }

    /**
     * Relationship with MedicalRecord.
     */
    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class, 'student_id', 'student_id');
    }

    /**
     * Relationship with Route stops.
     */
    public function stops(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'students_stop', 'student_id', 'route_id')
            ->withPivot('student_stop_id', 'stop_address', 'stop_order')
            ->withTimestamps();
    }

    /**
     * Relationship with FeeStructures.
     */
    public function feeStructures(): BelongsToMany
    {
        return $this->belongsToMany(FeeStructure::class, 'student_fee_assignment', 'student_id', 'fee_structure_id');
    }

    /**
     * Relationship with DailyAttendances.
     */
    public function dailyAttendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class, 'student_id', 'student_id');
    }
}
