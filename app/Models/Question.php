<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Question extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'evaluation_id',
        'type',
        'question_text',
        'options',
        'correct_answer',
        'explanation',
        'points',
        'order'
    ];

    protected $casts = [
        'options' => 'array',
        'points' => 'integer',
        'order' => 'integer'
    ];

    // ========== CONSTANTES ==========
    const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    const TYPE_FILL_BLANK = 'fill_blank';
    const TYPE_DRAG_DROP = 'drag_drop';
    const TYPE_FORMULA = 'formula';

    // ========== RELACIONES ==========
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }
} 