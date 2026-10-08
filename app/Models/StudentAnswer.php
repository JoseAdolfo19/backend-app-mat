<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Respuesta de un usuario a una pregunta dentro de un resultado de evaluación. */
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
    /** Usuario que envió la respuesta. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Resultado de evaluación que contiene la respuesta. */
    public function evaluationResult()
    {
        return $this->belongsTo(EvaluationResult::class);
    }

    /** Pregunta respondida. */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}