<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role',
        'username',
        'first_name',
        'last_name',
        'gender',
        'email',
        'password',
        'status',
        'profile_picture',
        'phone_number',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'last_login' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relationship with Guardian.
     */
    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class, 'user_id', 'user_id');
    }

    /**
     * Relationship with Driver.
     */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class, 'user_id', 'user_id');
    }

    /**
     * Relationship with DailyAttendance (recorded by this user).
     */
    public function dailyAttendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class, 'recorded_by', 'user_id');
    }
}
