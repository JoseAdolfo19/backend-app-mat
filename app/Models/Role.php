<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description'
    ];

    // ========== CONSTANTES ==========
    const ADMIN = 'admin';
    const TEACHER = 'teacher';
    const STUDENT = 'student';

    // ========== RELACIONES ==========
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // ========== SCOPES ==========
    public function scopeAdmin($query)
    {
        return $query->where('name', self::ADMIN);
    }

    public function scopeTeacher($query)
    {
        return $query->where('name', self::TEACHER);
    }

    public function scopeStudent($query)
    {
        return $query->where('name', self::STUDENT);
    }
}