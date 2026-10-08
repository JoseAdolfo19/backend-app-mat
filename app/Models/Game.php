<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Juego educativo asociado a un curso y administrado por un docente. */
class Game extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'course_id',
        'teacher_id',
        'title',
        'url',
        'pin',
        'description',
        'platform',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Curso al que pertenece el juego. */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /** Docente responsable del juego. */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Entregas de estudiantes para el juego. */
    public function submissions()
    {
        return $this->hasMany(GameSubmission::class);
    }
}