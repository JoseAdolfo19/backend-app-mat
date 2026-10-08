<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Mensaje enviado por un usuario dentro de una conversación. */
class Message extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'conversation_id',
        'sender_id',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /** Conversación a la que pertenece el mensaje. */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /** Usuario que envió el mensaje. */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}