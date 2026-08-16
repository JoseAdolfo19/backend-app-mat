<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AcademicEvent extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'type',
        'color',
        'all_day',
        'is_public',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'all_day' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('is_public', true);
        });
    }

    public function scopeInRange(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('start_date', [$start, $end]);
    }
}
