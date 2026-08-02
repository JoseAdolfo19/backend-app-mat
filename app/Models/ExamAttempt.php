<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ExamAttempt extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'exam_id',
        'student_id',
        'status',
        'score',
        'total_points',
        'answers',
        'time_spent',
        'tab_switch_count',
        'cheat_log',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'cheat_log' => 'array',
        'score' => 'integer',
        'total_points' => 'integer',
        'tab_switch_count' => 'integer',
        'time_spent' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ABANDONED = 'abandoned';
    const STATUS_CHEATING_DETECTED = 'cheating_detected';

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
