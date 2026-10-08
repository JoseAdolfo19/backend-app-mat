<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Examen creado por un docente, con preguntas e intentos de estudiantes. */
class Exam extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'description',
        'teacher_id',
        'unit',
        'difficulty',
        'time_limit',
        'max_attempts',
        'auto_correct',
        'randomize_questions',
        'is_active',
        'is_published',
        'total_questions',
        'total_points',
        'published_at',
    ];

    protected $casts = [
        'auto_correct' => 'boolean',
        'randomize_questions' => 'boolean',
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'max_attempts' => 'integer',
        'time_limit' => 'integer',
        'total_questions' => 'integer',
        'total_points' => 'integer',
        'published_at' => 'datetime',
    ];

    /** Docente responsable del examen. */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Preguntas incluidas en el examen. */
    public function questions()
    {
        return $this->hasMany(ExamQuestion::class);
    }

    /** Intentos realizados por estudiantes. */
    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /** Filtra los exámenes activos. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Filtra los exámenes inactivos, que este modelo usa como borradores. */
    public function scopeDraft($query)
    {
        return $query->where('is_active', false);
    }

    /** Filtra los exámenes asignados a un docente. */
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
}
