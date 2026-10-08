<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Publicación escrita por un usuario dentro de un hilo del foro. */
class ForumPost extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'thread_id',
        'user_id',
        'body',
    ];

    /** Hilo en el que se publicó el mensaje. */
    public function thread()
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
    }

    /** Usuario que escribió la publicación. */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}