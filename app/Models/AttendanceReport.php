<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceReport extends Model
{
    use HasFactory;

    protected $table = 'attendance_reports';
    protected $primaryKey = 'report_id';

    protected $fillable = [
        'generated_at',
        'total_present',
        'total_absent',
        'route_id',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Relationship with Route.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id', 'route_id');
    }
}
