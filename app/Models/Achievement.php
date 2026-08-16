<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    protected $fillable = [
        'slug',
        'name_es',
        'name_en',
        'name_qu',
        'description_es',
        'description_en',
        'description_qu',
        'icon',
        'xp_reward',
        'category',
        'criteria',
    ];

    protected $casts = [
        'criteria' => 'array',
        'xp_reward' => 'integer',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function name(string $locale = 'es'): string
    {
        return $this->{"name_{$locale}"} ?? $this->name_es;
    }

    public function description(string $locale = 'es'): string
    {
        return $this->{"description_{$locale}"} ?? $this->description_es;
    }
}
