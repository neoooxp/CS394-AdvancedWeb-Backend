<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $primaryKey = 'medical_record_id';

    protected $fillable = [
        'student_id',
        'medical_conditions',
        'special_needs',
        'emergency_notes',
    ];

    /**
     * Relationship with Student.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
