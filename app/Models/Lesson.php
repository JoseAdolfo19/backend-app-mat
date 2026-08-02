<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditLoggable;

class Lesson extends Model
{
    use HasUuids, SoftDeletes, AuditLoggable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'description',
        'content',
        'teacher_id',
        'unit',
        'topic',
        'difficulty',
        'tags',
        'estimated_time',
        'is_published',
        'published_at',
        'resources',
        'order',
        'views_count'
    ];

    protected $casts = [
        'tags' => 'array',
        'resources' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'estimated_time' => 'integer',
        'order' => 'integer',
        'views_count' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($lesson) {
            $lesson->logAudit('lesson.created', null, $lesson->only('id', 'title', 'teacher_id', 'difficulty', 'is_published'));
        });

        static::updated(function ($lesson) {
            $changes = $lesson->getChanges();
            $oldValues = [];
            foreach (array_keys($changes) as $key) {
                $oldValues[$key] = $lesson->getOriginal($key);
            }
            $lesson->logAudit('lesson.updated', $oldValues, $changes);
        });

        static::deleted(function ($lesson) {
            $lesson->logAudit('lesson.deleted', $lesson->only('id', 'title', 'teacher_id'), null);
        });
    }

    // ========== CONSTANTES ==========
    const DIFFICULTY_BASIC = 'basic';
    const DIFFICULTY_INTERMEDIATE = 'intermediate';
    const DIFFICULTY_ADVANCED = 'advanced';

    // ========== RELACIONES ==========
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function progress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    // ========== SCOPES ==========
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    public function scopeByUnit($query, $unit)
    {
        return $query->where('unit', $unit);
    }

    public function scopeByTopic($query, $topic)
    {
        return $query->where('topic', $topic);
    }
}