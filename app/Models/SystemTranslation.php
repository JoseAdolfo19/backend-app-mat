<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemTranslation extends Model
{
    public const LOCALES = ['es', 'en', 'qu'];

    protected $fillable = [
        'key',
        'locale',
        'value',
        'group',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
