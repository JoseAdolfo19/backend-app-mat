<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    // ========== SCOPES ==========

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }
}
