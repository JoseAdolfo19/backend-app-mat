<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TeacherProfile extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'department',
        'specialization',
        'years_experience',
        'students_count'
    ];

    protected $casts = [
        'years_experience' => 'integer',
        'students_count' => 'integer'
    ];

    // ========== RELACIONES ==========
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'teacher_id');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'teacher_id');
    } 
}