<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FeeStructure extends Model
{
    use HasFactory;

    protected $table = 'fee_structure';
    protected $primaryKey = 'fee_structure_id';

    protected $fillable = [
        'fee_name',
        'base_amount',
        'discount_percentage',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
        ];
    }

    /**
     * Relationship with Students.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_fee_assignment', 'fee_structure_id', 'student_id');
    }
}
