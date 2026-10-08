<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Curso de un salón, asignado a un docente y con estudiantes matriculados. */
class Course extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'salon_id',
        'name',
        'code',
        'description',
        'teacher_id',
    ];

    /** Salón al que pertenece el curso. */
    public function salon()
    {
        return $this->belongsTo(Salon::class, 'salon_id');
    }

    /** Docente responsable del curso. */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Lecciones incluidas en el curso. */
    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'course_id');
    }

    /** Matrículas registradas para el curso. */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'course_id');
    }

    /** Estudiantes matriculados en el curso. */
    public function students()
    {
        return $this->belongsToMany(User::class, 'enrollments', 'course_id', 'student_id')->withPivot('enrolled_by', 'created_at');
    }

    /** Genera un código de seis caracteres que no exista en otro curso. */
    public static function generateCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}