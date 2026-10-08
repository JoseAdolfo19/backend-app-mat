<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Matrícula de un estudiante en un curso y usuario que la registró. */
class Enrollment extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'course_id',
        'student_id',
        'enrolled_by',
    ];

    /** Curso en el que se matriculó el estudiante. */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /** Estudiante matriculado. */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** Usuario que creó la matrícula. */
    public function enrolledBy()
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}