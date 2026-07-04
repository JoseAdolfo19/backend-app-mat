<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evaluation extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'description',
        'teacher_id',
        'lesson_id',
        'type',
        'difficulty',
        'time_limit',
        'due_date',
        'is_published',
        'auto_correct',
        'randomize_questions',
        'max_attempts'
    ];

    protected $casts = [
        'time_limit' => 'integer',
        'due_date' => 'datetime',
        'is_published' => 'boolean',
        'auto_correct' => 'boolean',
        'randomize_questions' => 'boolean',
        'max_attempts' => 'integer'
    ];

    // ========== CONSTANTES ==========
    const TYPE_EXAM = 'exam';
    const TYPE_QUIZ = 'quiz';
    const TYPE_HOMEWORK = 'homework';
    const TYPE_PRACTICE = 'practice';

    const DIFFICULTY_BASIC = 'basic';
    const DIFFICULTY_INTERMEDIATE = 'intermediate';
    const DIFFICULTY_ADVANCED = 'advanced';

    // ========== RELACIONES ==========
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function results()
    {
        return $this->hasMany(EvaluationResult::class);
    }

    // ========== SCOPES ==========
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }
}