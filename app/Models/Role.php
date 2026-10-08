<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/** Rol que determina permisos y categoría de los usuarios. */
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
    const PARENT = 'parent';
    const DIRECTOR = 'director';
    const COORDINATOR = 'coordinador';

    // Roles que se comportan como docente (jerarquía de acceso a contenido)
    const TEACHING_ROLES = [self::TEACHER, self::COORDINATOR, self::DIRECTOR];

    // ========== RELACIONES ==========
    /** Usuarios asignados al rol. */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // ========== SCOPES ==========
    /** Filtra el rol de administrador. */
    public function scopeAdmin($query)
    {
        return $query->where('name', self::ADMIN);
    }

    /** Filtra el rol docente. */
    public function scopeTeacher($query)
    {
        return $query->where('name', self::TEACHER);
    }

    /** Filtra el rol de estudiante. */
    public function scopeStudent($query)
    {
        return $query->where('name', self::STUDENT);
    }

    /** Filtra el rol de padre o tutor. */
    public function scopeParent($query)
    {
        return $query->where('name', self::PARENT);
    }

    /** Filtra el rol de director. */
    public function scopeDirector($query)
    {
        return $query->where('name', self::DIRECTOR);
    }

    /** Filtra el rol de coordinador. */
    public function scopeCoordinator($query)
    {
        return $query->where('name', self::COORDINATOR);
    }
}