<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\EvaluationResult;
use App\Models\LessonProgress;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Support\Facades\Auth;

class GamificationService
{
    public const XP_LESSON_COMPLETED = 25;
    public const XP_EVALUATION_COMPLETED = 40;
    public const XP_PERFECT_SCORE = 20;
    public const XP_STREAK_BONUS = 10;
    public const XP_DAILY_LOGIN = 5;

    /**
     * Definición canónica de logros (slug => criterios).
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'first_lesson',
                'name_es' => 'Primera lección',
                'name_en' => 'First lesson',
                'name_qu' => 'Shuk yachay',
                'description_es' => 'Completa tu primera lección.',
                'description_en' => 'Complete your first lesson.',
                'description_qu' => 'Shuk yachayta tukuchiy.',
                'icon' => '📖',
                'xp_reward' => 25,
                'category' => 'lessons',
                'criteria' => ['completed_lessons' => 1],
            ],
            [
                'slug' => 'lesson_master',
                'name_es' => 'Maestro de lecciones',
                'name_en' => 'Lesson master',
                'name_qu' => 'Yachay kamachik',
                'description_es' => 'Completa 10 lecciones.',
                'description_en' => 'Complete 10 lessons.',
                'description_qu' => 'Chunka yachayta tukuchiy.',
                'icon' => '🏆',
                'xp_reward' => 100,
                'category' => 'lessons',
                'criteria' => ['completed_lessons' => 10],
            ],
            [
                'slug' => 'perfect_score',
                'name_es' => 'Puntuación perfecta',
                'name_en' => 'Perfect score',
                'name_qu' => 'Alli tupu',
                'description_es' => 'Consigue una puntuación perfecta en una evaluación.',
                'description_en' => 'Get a perfect score on an evaluation.',
                'description_qu' => 'Shuk taripaypi alli tupuyta hapiy.',
                'icon' => '⭐',
                'xp_reward' => 50,
                'category' => 'exams',
                'criteria' => ['perfect_score' => true],
            ],
            [
                'slug' => 'streak_7',
                'name_es' => 'Racha de 7 días',
                'name_en' => '7-day streak',
                'name_qu' => 'Kanchis punchaw racha',
                'description_es' => 'Estudia 7 días seguidos.',
                'description_en' => 'Study 7 days in a row.',
                'description_qu' => 'Kanchis punchaw yachakuy.',
                'icon' => '🔥',
                'xp_reward' => 75,
                'category' => 'streak',
                'criteria' => ['streak' => 7],
            ],
            [
                'slug' => 'streak_30',
                'name_es' => 'Racha de 30 días',
                'name_en' => '30-day streak',
                'name_qu' => 'Kimsa chunka punchaw racha',
                'description_es' => 'Estudia 30 días seguidos.',
                'description_en' => 'Study 30 days in a row.',
                'description_qu' => 'Kimsa chunka punchaw yachakuy.',
                'icon' => '⚡',
                'xp_reward' => 200,
                'category' => 'streak',
                'criteria' => ['streak' => 30],
            ],
            [
                'slug' => 'math_genius',
                'name_es' => 'Genio matemático',
                'name_en' => 'Math genius',
                'name_qu' => 'Yupay atiq',
                'description_es' => 'Consigue un promedio de 18 o más.',
                'description_en' => 'Achieve an average of 18 or more.',
                'description_qu' => 'Chunka pusak yallikta hapiy.',
                'icon' => '🧠',
                'xp_reward' => 250,
                'category' => 'exams',
                'criteria' => ['average' => 18],
            ],
            [
                'slug' => 'reach_level_5',
                'name_es' => 'Nivel 5 alcanzado',
                'name_en' => 'Reached level 5',
                'name_qu' => 'Pichka kaq pacha',
                'description_es' => 'Alcanza el nivel 5.',
                'description_en' => 'Reach level 5.',
                'description_qu' => 'Pichka kaq patata hapiy.',
                'icon' => '🥇',
                'xp_reward' => 150,
                'category' => 'level',
                'criteria' => ['level' => 5],
            ],
            [
                'slug' => 'first_login',
                'name_es' => 'Primer paso',
                'name_en' => 'First step',
                'name_qu' => 'Ñawpa puriy',
                'description_es' => 'Inicia sesión por primera vez.',
                'description_en' => 'Log in for the first time.',
                'description_qu' => 'Ñawpa kutipi haykuy.',
                'icon' => '👋',
                'xp_reward' => 10,
                'category' => 'general',
                'criteria' => ['first_login' => true],
            ],
        ];
    }

    /**
     * Sincroniza la definición de logros con la BD (seed idempotente).
     */
    public static function syncDefinitions(): void
    {
        foreach (self::definitions() as $def) {
            Achievement::updateOrCreate(['slug' => $def['slug']], $def);
        }
    }

    /**
     * Otorgar XP a un estudiante y devolver resultado.
     */
    public function awardXp(User $user, int $amount, string $reason = ''): array
    {
        $profile = $user->studentProfile;
        if (!$profile) {
            return ['leveled_up' => false, 'new_level' => null, 'total_xp' => 0, 'xp' => 0];
        }

        $oldLevel = $profile->level;
        $result = $profile->addXp($amount);
        $leveledUp = $result['new_level'] > $oldLevel;

        // Comprobar logros de nivel
        if ($leveledUp) {
            $this->checkAchievements($user);
        }

        if ($reason) {
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => 'xp_earned',
                'metadata' => ['amount' => $amount, 'reason' => $reason],
            ]);
        }

        return [
            'leveled_up' => $leveledUp,
            'new_level' => $result['new_level'],
            'total_xp' => $result['total_xp'],
            'xp' => $profile->xp,
        ];
    }

    /**
     * Comprobar todos los logros y otorgar los desbloqueados.
     * Devuelve los logros recién desbloqueados.
     */
    public function checkAchievements(User $user): array
    {
        $profile = $user->studentProfile;
        if (!$profile) {
            return [];
        }

        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->count();

        $perfectScore = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('score', '>=', 19.9)
            ->exists();

        $average = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->avg('score');

        $unlocked = UserAchievement::where('user_id', $user->id)->pluck('achievement_id')->all();
        $newly = [];

        foreach (Achievement::all() as $achievement) {
            if (in_array($achievement->id, $unlocked)) {
                continue;
            }

            $criteria = $achievement->criteria ?? [];
            $met = false;

            if (isset($criteria['completed_lessons']) && $completedLessons >= $criteria['completed_lessons']) {
                $met = true;
            }
            if (isset($criteria['streak']) && $profile->current_streak >= $criteria['streak']) {
                $met = true;
            }
            if (isset($criteria['level']) && $profile->level >= $criteria['level']) {
                $met = true;
            }
            if (!empty($criteria['perfect_score']) && $perfectScore) {
                $met = true;
            }
            if (isset($criteria['average']) && ($average ?? 0) >= $criteria['average']) {
                $met = true;
            }
            if (!empty($criteria['first_login'])) {
                $met = true;
            }

            if ($met) {
                UserAchievement::create([
                    'user_id' => $user->id,
                    'achievement_id' => $achievement->id,
                    'unlocked_at' => now(),
                ]);
                $newly[] = $achievement;
                $this->awardXp($user, $achievement->xp_reward, 'achievement:' . $achievement->slug);
            }
        }

        return $newly;
    }

    /**
     * Resumen de gamificación para un estudiante.
     */
    public function gamificationSummary(User $user, string $locale = 'es'): array
    {
        $profile = $user->studentProfile;
        if (!$profile) {
            return ['available' => false];
        }

        $achievements = Achievement::with(['users' => function ($q) use ($user) {
            $q->where('users.id', $user->id);
        }])->get()->map(function ($a) use ($locale, $user) {
            $unlocked = $a->users->isNotEmpty();
            return [
                'slug' => $a->slug,
                'name' => $a->name($locale),
                'description' => $a->description($locale),
                'icon' => $a->icon,
                'xp_reward' => $a->xp_reward,
                'category' => $a->category,
                'unlocked' => $unlocked,
                'unlocked_at' => $unlocked ? $a->users->first()->pivot->unlocked_at : null,
            ];
        });

        return [
            'available' => true,
            'level' => $profile->level,
            'total_xp' => $profile->total_xp,
            'rank_points' => $profile->rank_points,
            'level_progress' => $profile->levelProgress(),
            'achievements' => $achievements,
            'unlocked_count' => $achievements->where('unlocked', true)->count(),
            'total_count' => $achievements->count(),
            'badges' => $profile->badges ?? [],
        ];
    }
}
