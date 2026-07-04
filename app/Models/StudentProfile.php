<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StudentProfile extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'academic_level',
        'total_lessons_completed',
        'average_score',
        'total_time_spent',
        'current_streak',
        'badges'
    ];

    protected $casts = [
        'badges' => 'array',
        'average_score' => 'float',
        'total_time_spent' => 'integer',
        'total_lessons_completed' => 'integer',
        'current_streak' => 'integer'
    ];

    // ========== RELACIONES ==========
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}