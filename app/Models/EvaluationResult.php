<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EvaluationResult extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'evaluation_id',
        'score',
        'max_score',
        'correct_answers',
        'total_questions',
        'time_taken',
        'status',
        'started_at',
        'completed_at',
        'attempt_number'
    ];

    protected $casts = [
        'score' => 'float',
        'max_score' => 'float',
        'correct_answers' => 'integer',
        'total_questions' => 'integer',
        'time_taken' => 'integer',
        'attempt_number' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    // ========== CONSTANTES ==========
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // ========== RELACIONES ==========
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }

    // ========== SCOPES ==========
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}