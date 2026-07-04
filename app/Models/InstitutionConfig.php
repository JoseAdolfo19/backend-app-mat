<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class InstitutionConfig extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'institution_name',
        'primary_color',
        'secondary_color',
        'logo',
        'email_notifications',
        'backup_frequency',
        'last_backup'
    ];

    protected $casts = [
        'email_notifications' => 'array',
        'last_backup' => 'datetime'
    ];
}