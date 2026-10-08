<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Trabajo enviado por un estudiante para una lección, evaluación o examen. */
class SubmittedWork extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'student_id',
        'lesson_id',
        'evaluation_id',
        'exam_id',
        'work_type',
        'title',
        'description',
        'status',
        'score',
        'max_score',
        'teacher_feedback',
        'attachments',
        'submitted_at',
        'graded_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'score' => 'integer',
        'max_score' => 'integer',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    // ========== RELACIONES ==========

    /** Estudiante que envió el trabajo. */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** Lección relacionada con el trabajo, si corresponde. */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /** Evaluación relacionada con el trabajo, si corresponde. */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** Examen relacionado con el trabajo, si corresponde. */
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    // ========== SCOPES ==========

    /** Limita la consulta a los trabajos de un estudiante. */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /** Filtra los trabajos con estado pendiente. */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /** Filtra los trabajos enviados. */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /** Filtra los trabajos calificados. */
    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }
}
