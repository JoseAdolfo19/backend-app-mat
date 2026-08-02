<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions()
    {
        return $this->hasMany(ExamQuestion::class);
    }

    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDraft($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
}
