<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentStop extends Model
{
    use HasFactory;

    protected $table = 'students_stop';
    protected $primaryKey = 'student_stop_id';

    protected $fillable = [
        'student_id',
        'route_id',
        'stop_address',
        'stop_order',
    ];

    /**
     * Relationship with Student.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * Relationship with Route.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id', 'route_id');
    }
}
