<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StudentAnswer extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'evaluation_result_id',
        'question_id',
        'answer',
        'is_correct',
        'points_earned'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'points_earned' => 'integer'
    ];

    // ========== RELACIONES ==========
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function evaluationResult()
    {
        return $this->belongsTo(EvaluationResult::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}