<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Salón académico con período, coordinador, cursos y usuarios asignados. */
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

    /** Período académico al que pertenece el salón. */
    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    /** Usuario coordinador del salón. */
    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    /** Cursos que pertenecen al salón. */
    public function courses()
    {
        return $this->hasMany(Course::class, 'salon_id');
    }

    /** Docentes asociados al salón a través de sus cursos. */
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'courses', 'salon_id', 'teacher_id')->distinct();
    }

    /** Estudiantes asignados al salón. */
    public function students()
    {
        return $this->hasMany(User::class, 'salon_id');
    }

    /** Nombre visible formado por el grado y la sección. */
    public function getDisplayNameAttribute()
    {
        return trim("{$this->grade} \"{$this->section}\"");
    }
}