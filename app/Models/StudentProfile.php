<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StudentProfile extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'academic_level',
        'total_lessons_completed',
        'average_score',
        'total_time_spent',
        'current_streak',
        'last_activity_date',
        'badges',
        'xp',
        'total_xp',
        'level',
        'rank_points'
    ];

    protected $casts = [
        'badges' => 'array',
        'average_score' => 'float',
        'total_time_spent' => 'integer',
        'total_lessons_completed' => 'integer',
        'current_streak' => 'integer',
        'last_activity_date' => 'date',
        'xp' => 'integer',
        'total_xp' => 'integer',
        'level' => 'integer',
        'rank_points' => 'integer'
    ];

    /**
     * XP acumulado necesario para alcanzar un nivel (progresión triangular: 100/200/300...).
     */
    public static function cumulativeXpForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }
        return 100 * ($level - 1) * $level / 2;
    }

    /**
     * Nivel derivado del total_xp.
     */
    public static function levelFromXp(int $totalXp): int
    {
        $level = 1;
        while (self::cumulativeXpForLevel($level + 1) <= $totalXp) {
            $level++;
        }
        return $level;
    }

    /**
     * Progreso (0-100) hacia el siguiente nivel basado en total_xp.
     */
    public function levelProgress(): array
    {
        $level = self::levelFromXp($this->total_xp);
        $base = self::cumulativeXpForLevel($level);
        $next = self::cumulativeXpForLevel($level + 1);
        $span = max(1, $next - $base);
        $progress = (int) round((($this->total_xp - $base) / $span) * 100);

        return [
            'level' => $level,
            'total_xp' => $this->total_xp,
            'xp_in_level' => $this->total_xp - $base,
            'xp_for_current_level' => $base,
            'xp_for_next_level' => $next,
            'progress_percent' => max(0, min(100, $progress)),
            'xp_to_next_level' => max(0, $next - $this->total_xp),
        ];
    }

    /**
     * Añade XP, actualiza total_xp/nivel y devuelve el resultado.
     */
    public function addXp(int $amount): array
    {
        $this->xp += $amount;
        $this->total_xp += $amount;
        $this->level = self::levelFromXp($this->total_xp);
        $this->save();

        return ['new_level' => $this->level, 'total_xp' => $this->total_xp];
    }

    // ========== RELACIONES ==========
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}