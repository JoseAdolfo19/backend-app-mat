<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'email',
        'password',
        'full_name',
        'role_id',
        'is_active',
        'last_login',
        'profile_image',
        'institution',
        'grade',
        'google_id',
        'google_token',
        'provider',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    // ========== RELACIONES ==========
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function teacherProfile()
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'teacher_id');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'teacher_id');
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function evaluationResults()
    {
        return $this->hasMany(EvaluationResult::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ========== MÉTODOS DE AYUDA ==========
    public function isAdmin()
    {
        return $this->role->name === Role::ADMIN;
    }

    public function isTeacher()
    {
        return $this->role->name === Role::TEACHER;
    }

    public function isStudent()
    {
        return $this->role->name === Role::STUDENT;
    }

    public function hasRole($roleName)
    {
        return $this->role->name === $roleName;
    }

    public function hasAnyRole($roles)
    {
        return in_array($this->role->name, $roles);
    }

    public function isGoogleUser()
    {
        return $this->provider === 'google';
    }
} 