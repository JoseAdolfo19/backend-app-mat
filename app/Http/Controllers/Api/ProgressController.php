<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Evaluation;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\EvaluationResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    /**
     * Dashboard del estudiante
     */
    public function studentDashboard()
    {
        $user = Auth::user();
        $studentProfile = $user->studentProfile;

        // Lecciones en curso
        $inProgressLessons = LessonProgress::where('user_id', $user->id)
            ->whereIn('status', [LessonProgress::STATUS_IN_PROGRESS, LessonProgress::STATUS_NOT_STARTED])
            ->with('lesson')
            ->get();

        // Lecciones completadas recientemente
        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->with('lesson')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        // Últimas evaluaciones
        $recentEvaluations = EvaluationResult::where('user_id', $user->id)
            ->with('evaluation')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Estadísticas del estudiante
        $stats = [
            'total_lessons' => Lesson::published()->count(),
            'completed_lessons' => LessonProgress::where('user_id', $user->id)
                ->where('status', LessonProgress::STATUS_COMPLETED)
                ->count(),
            'in_progress_lessons' => LessonProgress::where('user_id', $user->id)
                ->where('status', LessonProgress::STATUS_IN_PROGRESS)
                ->count(),
            'average_score' => $studentProfile->average_score ?? 0,
            'current_streak' => $studentProfile->current_streak ?? 0,
            'total_time_spent' => $studentProfile->total_time_spent ?? 0,
            'badges' => $studentProfile->badges ?? []
        ];

        // Próximas evaluaciones
        $upcomingEvaluations = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with('evaluation')
            ->whereHas('evaluation', function($query) {
                $query->where('due_date', '>=', now());
            })
            ->orderBy('created_at', 'asc')
            ->limit(3)
            ->get();

        return response()->json([
            'user' => $user->load('role'),
            'profile' => $studentProfile,
            'stats' => $stats,
            'in_progress_lessons' => $inProgressLessons,
            'completed_lessons' => $completedLessons,
            'recent_evaluations' => $recentEvaluations,
            'upcoming_evaluations' => $upcomingEvaluations
        ]);
    }

    /**
     * Dashboard del docente
     */
    public function teacherDashboard()
    {
        $user = Auth::user();

        // Obtener todas las evaluaciones del docente
        $evaluationIds = Evaluation::where('teacher_id', $user->id)->pluck('id');
        
        // Estadísticas del docente
        $stats = [
            'total_students' => User::where('role_id', 3)->count(),
            'total_lessons' => Lesson::where('teacher_id', $user->id)->count(),
            'total_evaluations' => Evaluation::where('teacher_id', $user->id)->count(),
            'published_lessons' => Lesson::where('teacher_id', $user->id)
                ->where('is_published', true)
                ->count(),
            'published_evaluations' => Evaluation::where('teacher_id', $user->id)
                ->where('is_published', true)
                ->count(),
            'total_submissions' => EvaluationResult::whereIn('evaluation_id', $evaluationIds)->count(),
            'average_score' => EvaluationResult::whereIn('evaluation_id', $evaluationIds)
                ->where('status', 'completed')
                ->avg('score') ?? 0,
            'pending_reviews' => EvaluationResult::whereIn('evaluation_id', $evaluationIds)
                ->where('status', 'pending')
                ->count()
        ];

        // Últimos estudiantes activos
        $recentStudents = User::where('role_id', 3)
            ->whereNotNull('last_login')
            ->orderBy('last_login', 'desc')
            ->limit(10)
            ->get();

        // Evaluaciones recientes
        $recentEvaluations = Evaluation::where('teacher_id', $user->id)
            ->with('results')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'user' => $user->load('role', 'teacherProfile'),
            'stats' => $stats,
            'recent_students' => $recentStudents,
            'recent_evaluations' => $recentEvaluations
        ]);
    }

    /**
     * Obtener progreso de una lección específica
     */
    public function getLessonProgress($lessonId)
    {
        $lesson = Lesson::findOrFail($lessonId);
        
        $progress = LessonProgress::where('user_id', Auth::id())
            ->where('lesson_id', $lessonId)
            ->first();

        if (!$progress) {
            // Crear progreso inicial
            $progress = LessonProgress::create([
                'id' => Str::uuid(),
                'user_id' => Auth::id(),
                'lesson_id' => $lessonId,
                'progress' => 0,
                'status' => LessonProgress::STATUS_NOT_STARTED,
                'time_spent' => 0,
                'last_position' => 0
            ]);
        }

        return response()->json([
            'lesson' => $lesson,
            'progress' => $progress
        ]);
    }

    /**
     * Actualizar progreso de una lección
     */
    public function updateLessonProgress(Request $request, $lessonId)
    {
        $validated = $request->validate([
            'progress' => 'required|integer|min:0|max:100',
            'time_spent' => 'nullable|integer|min:0',
            'last_position' => 'nullable|integer|min:0'
        ]);

        $lesson = Lesson::findOrFail($lessonId);

        $progress = LessonProgress::where('user_id', Auth::id())
            ->where('lesson_id', $lessonId)
            ->first();

        if (!$progress) {
            $progress = LessonProgress::create([
                'id' => Str::uuid(),
                'user_id' => Auth::id(),
                'lesson_id' => $lessonId,
                'progress' => 0,
                'status' => LessonProgress::STATUS_NOT_STARTED,
                'time_spent' => 0,
                'last_position' => 0
            ]);
        }

        $updateData = [
            'progress' => $validated['progress']
        ];

        if (isset($validated['time_spent'])) {
            $updateData['time_spent'] = ($progress->time_spent ?? 0) + $validated['time_spent'];
        }

        if (isset($validated['last_position'])) {
            $updateData['last_position'] = $validated['last_position'];
        }

        // Actualizar estado según el progreso
        if ($validated['progress'] >= 100) {
            $updateData['status'] = LessonProgress::STATUS_COMPLETED;
            $updateData['completed_at'] = now();
            
            // Actualizar perfil del estudiante
            $this->updateStudentProfileOnCompletion($progress);
        } elseif ($validated['progress'] > 0) {
            $updateData['status'] = LessonProgress::STATUS_IN_PROGRESS;
        }

        $progress->update($updateData);

        // Verificar y otorgar insignias
        $badges = $this->checkAndAwardBadges();

        return response()->json([
            'message' => 'Progreso actualizado exitosamente',
            'progress' => $progress,
            'badges' => $badges
        ]);
    }

    /**
     * Obtener estadísticas de progreso del usuario
     */
    public function getMyStats()
    {
        $user = Auth::user();
        
        // Progreso general
        $totalLessons = Lesson::published()->count();
        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->count();

        // Tiempo total
        $totalTime = LessonProgress::where('user_id', $user->id)
            ->sum('time_spent');

        // Evaluaciones 
        $totalEvaluations = Evaluation::published()->count();
        $completedEvaluations = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        // Rendimiento
        $averageScore = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->avg('score') ?? 0;

        // Distribución de progreso por lección
        $progressDistribution = LessonProgress::where('user_id', $user->id)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Actividad reciente (últimos 7 días)
        $recentActivity = $this->getRecentActivity($user->id);

        return response()->json([
            'summary' => [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'completion_rate' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : 0,
                'total_time_spent' => $totalTime,
                'total_evaluations' => $totalEvaluations,
                'completed_evaluations' => $completedEvaluations,
                'average_score' => round($averageScore, 2)
            ],
            'progress_distribution' => $progressDistribution,
            'recent_activity' => $recentActivity
        ]);
    }

    /**
     * Obtener insignias del usuario
     */
    public function getBadges()
    {
        $user = Auth::user();
        $studentProfile = $user->studentProfile;

        $badges = $studentProfile->badges ?? [];

        // Definir todas las insignias disponibles
        $availableBadges = [
            [
                'id' => 'first_lesson',
                'name' => 'Primera Lección',
                'description' => 'Completaste tu primera lección',
                'icon' => '🎓'
            ],
            [
                'id' => 'lesson_master',
                'name' => 'Maestro de Lecciones',
                'description' => 'Completaste 10 lecciones',
                'icon' => '📚'
            ],
            [
                'id' => 'perfect_score',
                'name' => 'Puntuación Perfecta',
                'description' => 'Obtuviste 10/10 en una evaluación',
                'icon' => '⭐'
            ],
            [
                'id' => 'streak_7',
                'name' => 'Racha de 7 Días',
                'description' => 'Mantuviste una racha de 7 días',
                'icon' => '🔥'
            ],
            [
                'id' => 'streak_30',
                'name' => 'Racha de 30 Días',
                'description' => 'Mantuviste una racha de 30 días',
                'icon' => '💎'
            ],
            [
                'id' => 'math_genius',
                'name' => 'Genio Matemático',
                'description' => 'Promedio superior a 9 en todas las evaluaciones',
                'icon' => '🧠'
            ]
        ];

        // Marcar insignias obtenidas
        foreach ($availableBadges as &$badge) {
            $badge['unlocked'] = in_array($badge['id'], $badges);
        }

        return response()->json([
            'badges' => $availableBadges,
            'unlocked_count' => count($badges),
            'total_badges' => count($availableBadges)
        ]);
    }

    // ========== MÉTODOS PRIVADOS ==========

    /**
     * Actualizar perfil del estudiante al completar una lección
     */
    private function updateStudentProfileOnCompletion($progress)
    {
        $studentProfile = Auth::user()->studentProfile;
        if ($studentProfile) {
            $studentProfile->increment('total_lessons_completed');
            $studentProfile->increment('current_streak');
            
            // Actualizar tiempo total
            if ($progress->time_spent) {
                $studentProfile->increment('total_time_spent', $progress->time_spent);
            }

            // Calcular nuevo promedio
            $this->updateAverageScore();
            
            $studentProfile->save();
        }
    }

    /**
     * Actualizar puntaje promedio del estudiante
     */
    private function updateAverageScore()
    {
        $average = EvaluationResult::where('user_id', Auth::id())
            ->where('status', 'completed')
            ->avg('score');

        $studentProfile = Auth::user()->studentProfile;
        if ($studentProfile) {
            $studentProfile->average_score = round($average ?? 0, 2);
            $studentProfile->save();
        }
    }

    /**
     * Verificar y otorgar insignias
     */
    private function checkAndAwardBadges()
    {
        $user = Auth::user();
        $studentProfile = $user->studentProfile;
        $badges = $studentProfile->badges ?? [];
        $newBadges = [];

        // Verificar insignias
        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->count();

        // Primera lección
        if ($completedLessons >= 1 && !in_array('first_lesson', $badges)) {
            $badges[] = 'first_lesson';
            $newBadges[] = 'first_lesson';
        }

        // Maestro de lecciones
        if ($completedLessons >= 10 && !in_array('lesson_master', $badges)) {
            $badges[] = 'lesson_master';
            $newBadges[] = 'lesson_master';
        }

        // Racha de 7 días
        if ($studentProfile->current_streak >= 7 && !in_array('streak_7', $badges)) {
            $badges[] = 'streak_7';
            $newBadges[] = 'streak_7';
        }

        // Racha de 30 días
        if ($studentProfile->current_streak >= 30 && !in_array('streak_30', $badges)) {
            $badges[] = 'streak_30';
            $newBadges[] = 'streak_30';
        }

        // Puntuación perfecta
        $perfectScore = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('score', '>=', 9.9)
            ->exists();

        if ($perfectScore && !in_array('perfect_score', $badges)) {
            $badges[] = 'perfect_score';
            $newBadges[] = 'perfect_score';
        }

        // Genio matemático
        $averageScore = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->avg('score');

        if ($averageScore >= 9 && !in_array('math_genius', $badges)) {
            $badges[] = 'math_genius';
            $newBadges[] = 'math_genius';
        }

        // Guardar insignias
        $studentProfile->badges = $badges;
        $studentProfile->save();

        // Crear notificaciones para nuevas insignias
        foreach ($newBadges as $badgeId) {
            $this->createBadgeNotification($user->id, $badgeId);
        }

        return $newBadges;
    }

    /**
     * Crear notificación de insignia
     */
    private function createBadgeNotification($userId, $badgeId)
    {
        $badgeNames = [
            'first_lesson' => '¡Primera Lección Completada! 🎓',
            'lesson_master' => '¡Maestro de Lecciones! 📚',
            'perfect_score' => '¡Puntuación Perfecta! ⭐',
            'streak_7' => '¡Racha de 7 Días! 🔥',
            'streak_30' => '¡Racha de 30 Días! 💎',
            'math_genius' => '¡Genio Matemático! 🧠'
        ];

        NotificationController::createNotification(
            $userId, 
            'Nueva Insignia Desbloqueada',
            $badgeNames[$badgeId] ?? 'Has desbloqueado una nueva insignia',
            'success'
        );
    }

    /**
     * Obtener actividad reciente
     */
    private function getRecentActivity($userId)
    {
        $activity = [];

        // Lecciones completadas recientemente
        $recentLessons = LessonProgress::where('user_id', $userId)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->with('lesson')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentLessons as $lesson) {
            $activity[] = [
                'type' => 'lesson_completed',
                'title' => 'Lección completada: ' . $lesson->lesson->title,
                'date' => $lesson->completed_at,
                'icon' => '📖'
            ];
        }

        // Evaluaciones completadas recientemente
        $recentEvaluations = EvaluationResult::where('user_id', $userId)
            ->where('status', 'completed')
            ->with('evaluation')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentEvaluations as $evaluation) {
            $activity[] = [
                'type' => 'evaluation_completed',
                'title' => 'Evaluación completada: ' . $evaluation->evaluation->title . ' (Puntuación: ' . $evaluation->score . ')',
                'date' => $evaluation->completed_at,
                'icon' => '📝'
            ];
        }

        // Ordenar por fecha
        usort($activity, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($activity, 0, 10);
    }
}