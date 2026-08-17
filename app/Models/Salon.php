<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Salon extends Model
{
    use HasUuids;

    protected $table = 'salones';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'grade',
        'section',
        'academic_period_id',
        'coordinator_id',
    ];

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'salon_id');
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'courses', 'salon_id', 'teacher_id')->distinct();
    }

    public function getDisplayNameAttribute()
    {
        return trim("{$this->grade} \"{$this->section}\"");
    }
}