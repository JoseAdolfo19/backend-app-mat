<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Registro del desbloqueo de un logro por un usuario. */
class UserAchievement extends Model
{
    protected $fillable = [
        'user_id',
        'achievement_id',
        'unlocked_at',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
    ];

    /** Logro desbloqueado. */
    public function achievement()
    {
        return $this->belongsTo(Achievement::class);
    }

    /** Usuario que desbloqueó el logro. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
