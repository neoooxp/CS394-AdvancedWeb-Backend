<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guardian extends Model
{
    use HasFactory;

    protected $primaryKey = 'guardian_id';

    protected $fillable = [
        'user_id',
        'guardian_code',
        'address',
    ];

    /**
     * Relationship with User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Relationship with Students.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians', 'guardian_id', 'student_id')
            ->withPivot('relationship_type')
            ->withTimestamps();
    }

    /**
     * Relationship with Invoices.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'guardian_id', 'guardian_id');
    }
}
