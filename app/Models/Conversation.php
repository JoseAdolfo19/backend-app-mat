<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Conversación entre un docente y un estudiante, opcionalmente vinculada a una evaluación. */
class Conversation extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'teacher_id',
        'student_id',
        'evaluation_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /** Docente participante en la conversación. */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Estudiante participante en la conversación. */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** Evaluación a la que se refiere la conversación, si está asociada. */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** Mensajes que pertenecen a la conversación. */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}