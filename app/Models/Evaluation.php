<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditLoggable;

class Evaluation extends Model
{
    use HasUuids, SoftDeletes, AuditLoggable;

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
        'max_attempts',
        'published_at',
        'total_questions',
        'total_points'
    ];

    protected $casts = [
        'time_limit' => 'integer',
        'due_date' => 'datetime',
        'is_published' => 'boolean',
        'auto_correct' => 'boolean',
        'randomize_questions' => 'boolean',
        'max_attempts' => 'integer',
        'published_at' => 'datetime',
        'total_questions' => 'integer',
        'total_points' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($evaluation) {
            $evaluation->logAudit('evaluation.created', null, $evaluation->only('id', 'title', 'teacher_id', 'lesson_id', 'type', 'difficulty', 'is_published'));
        });

        static::updated(function ($evaluation) {
            $changes = $evaluation->getChanges();
            $oldValues = [];
            foreach (array_keys($changes) as $key) {
                $oldValues[$key] = $evaluation->getOriginal($key);
            }
            $evaluation->logAudit('evaluation.updated', $oldValues, $changes);
        });

        static::deleted(function ($evaluation) {
            $evaluation->logAudit('evaluation.deleted', $evaluation->only('id', 'title', 'teacher_id', 'lesson_id'), null);
        });
    }

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