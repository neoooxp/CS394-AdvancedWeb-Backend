<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGuardian extends Model
{
    use HasFactory;

    protected $table = 'student_guardians';
    protected $primaryKey = 'student_guardian_id';

    protected $fillable = [
        'guardian_id',
        'student_id',
        'relationship_type',
    ];

    /**
     * Relationship with Guardian.
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'guardian_id', 'guardian_id');
    }

    /**
     * Relationship with Student.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
