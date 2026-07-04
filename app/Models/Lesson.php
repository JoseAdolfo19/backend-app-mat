<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasUuids, SoftDeletes;

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
        'resources',
        'order'
    ];

    protected $casts = [
        'tags' => 'array',
        'resources' => 'array',
        'is_published' => 'boolean',
        'estimated_time' => 'integer',
        'order' => 'integer'
    ];

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