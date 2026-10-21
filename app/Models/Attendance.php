<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Registro de asistencia de un estudiante en un curso y fecha determinados. */
class Attendance extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = 'attendance';

    protected $fillable = [
        'id',
        'course_id',
        'student_id',
        'date',
        'status',
        'note',
        'recorded_by'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    // ========== CONSTANTES ==========
    const STATUS_PRESENT = 'present';
    const STATUS_ABSENT = 'absent';
    const STATUS_LATE = 'late';
    const STATUS_EXCUSED = 'excused';

    // ========== RELACIONES ==========
    /** Curso al que pertenece el registro de asistencia. */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /** Estudiante al que corresponde la asistencia. */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** Usuario docente o staff que registró la asistencia. */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
