<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ForumThread extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'teacher_id',
        'lesson_id',
        'title',
        'body',
        'status',
    ];

    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function posts()
    {
        return $this->hasMany(ForumPost::class, 'thread_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    protected function isClosed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === self::STATUS_CLOSED
        );
    }
}