<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class GameSubmission extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'game_id',
        'student_id',
        'score',
        'screenshot_url',
        'status',
        'grade',
        'teacher_feedback',
        'xp_awarded',
        'submitted_at',
        'graded_at',
    ];

    protected $casts = [
        'grade' => 'decimal:2',
        'xp_awarded' => 'integer',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}