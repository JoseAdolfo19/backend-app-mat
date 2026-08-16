<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\AuditLoggable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, AuditLoggable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'email',
        'password',
        'full_name',
        'dni',
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

    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            $user->logAudit('user.created', null, $user->only('id', 'full_name', 'email', 'role_id', 'is_active', 'provider'));
        });

        static::updated(function ($user) {
            $changes = $user->getChanges();
            $oldValues = [];
            foreach (array_keys($changes) as $key) {
                $oldValues[$key] = $user->getOriginal($key);
            }
            $user->logAudit('user.updated', $oldValues, $changes);
        });

        static::deleted(function ($user) {
            $user->logAudit('user.deleted', $user->only('id', 'full_name', 'email'), null);
        });
    }

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

    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function academicEvents()
    {
        return $this->hasMany(AcademicEvent::class);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    // ========== MÉTODOS DE AYUDA ==========
    public function isAdmin()
    {
        return $this->role?->name === Role::ADMIN;
    }

    public function isTeacher()
    {
        return $this->role?->name === Role::TEACHER;
    }

    public function isStudent()
    {
        return $this->role?->name === Role::STUDENT;
    }

    public function isParent()
    {
        return $this->role?->name === Role::PARENT;
    }

    public function children()
    {
        return $this->belongsToMany(User::class, 'parent_student', 'parent_id', 'student_id');
    }

    public function parents()
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'parent_id');
    }

    public function hasRole($roleName)
    {
        return $this->role?->name === $roleName;
    }

    public function hasAnyRole($roles)
    {
        return in_array($this->role?->name, $roles);
    }

    public function isGoogleUser()
    {
        return $this->provider === 'google';
    }
} 