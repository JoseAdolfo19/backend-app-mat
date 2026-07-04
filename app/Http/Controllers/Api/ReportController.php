<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Lesson;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Reporte de rendimiento general
     */
    public function performanceReport(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:week,month,quarter,year',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date'
        ]);

        $query = EvaluationResult::query();

        // Filtros de fecha
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        } elseif ($request->has('period')) {
            $query->whereBetween('created_at', $this->getPeriodDateRange($request->period));
        }

        // Si es docente, solo ver sus estudiantes
        if (Auth::user()->isTeacher()) {
            $studentIds = User::where('role_id', 3)->pluck('id');
            $query->whereIn('user_id', $studentIds);
        }

        // Estadísticas
        $stats = [
            'total_evaluations' => $query->count(),
            'average_score' => $query->avg('score'),
            'total_students' => $query->distinct('user_id')->count('user_id'),
            'passing_rate' => $this->calculatePassingRate($query),
            'top_performers' => $this->getTopPerformers($query),
            'difficulty_areas' => $this->getDifficultyAreas($query)
        ];

        return response()->json($stats);
    }

    /**
     * Reporte de calificaciones
     */
    public function gradesReport(Request $request)
    {
        $request->validate([
            'evaluation_id' => 'nullable|exists:evaluations,id',
            'student_id' => 'nullable|exists:users,id',
            'period' => 'nullable|in:week,month,quarter,year'
        ]);

        $query = EvaluationResult::with(['user', 'evaluation'])
            ->where('status', 'completed');

        // Filtros
        if ($request->has('evaluation_id')) {
            $query->where('evaluation_id', $request->evaluation_id);
        }

        if ($request->has('student_id')) {
            $query->where('user_id', $request->student_id);
        }

        if ($request->has('period')) {
            $query->whereBetween('created_at', $this->getPeriodDateRange($request->period));
        }

        // Si es docente, solo ver sus evaluaciones
        if (Auth::user()->isTeacher()) {
            $evaluationIds = Evaluation::where('teacher_id', Auth::id())->pluck('id');
            $query->whereIn('evaluation_id', $evaluationIds);
        }

        $results = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        // Resumen estadístico
        $summary = [
            'average' => $query->avg('score'),
            'max' => $query->max('score'),
            'min' => $query->min('score'),
            'total' => $query->count()
        ];

        return response()->json([
            'results' => $results,
            'summary' => $summary
        ]);
    }

    /**
     * Reporte de un estudiante específico
     */
    public function studentReport($userId)
    {
        $user = User::with(['studentProfile'])->findOrFail($userId);

        if (Auth::user()->isTeacher()) {
            // Verificar que el estudiante pertenezca al docente
            $evaluationIds = Evaluation::where('teacher_id', Auth::id())->pluck('id');
            $hasResults = EvaluationResult::where('user_id', $userId)
                ->whereIn('evaluation_id', $evaluationIds)
                ->exists();

            if (!$hasResults) {
                return response()->json([
                    'message' => 'No tienes acceso a los datos de este estudiante'
                ], 403);
            }
        }

        // Progreso de lecciones
        $lessonProgress = LessonProgress::where('user_id', $userId)
            ->with('lesson')
            ->get();

        // Resultados de evaluaciones
        $evaluationResults = EvaluationResult::where('user_id', $userId)
            ->with('evaluation')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        // Estadísticas del estudiante
        $stats = [
            'total_lessons_completed' => $lessonProgress->where('status', 'completed')->count(),
            'total_lessons_in_progress' => $lessonProgress->where('status', 'in_progress')->count(),
            'average_score' => $evaluationResults->avg('score') ?? 0,
            'total_evaluations' => $evaluationResults->count(),
            'best_score' => $evaluationResults->max('score') ?? 0,
            'worst_score' => $evaluationResults->min('score') ?? 0,
            'current_streak' => $user->studentProfile->current_streak ?? 0,
            'badges' => $user->studentProfile->badges ?? []
        ];

        // Análisis de fortalezas y debilidades
        $strengths = $this->analyzeStrengths($evaluationResults);

        return response()->json([
            'student' => $user,
            'stats' => $stats,
            'lesson_progress' => $lessonProgress,
            'evaluation_results' => $evaluationResults,
            'strengths' => $strengths
        ]);
    }

    /**
     * Exportar reporte en PDF
     */
    public function exportPDF(Request $request)
    {
        // Aquí se implementaría la generación de PDF con DomPDF
        // Por ahora retornamos un mensaje
        return response()->json([
            'message' => 'Función de exportación PDF en desarrollo',
            'data' => $request->all()
        ]);
    }

    /**
     * Exportar reporte en Excel
     */
    public function exportExcel(Request $request)
    {
        // Aquí se implementaría la generación de Excel con Maatwebsite
        // Por ahora retornamos un mensaje
        return response()->json([
            'message' => 'Función de exportación Excel en desarrollo',
            'data' => $request->all()
        ]);
    }

    // ========== MÉTODOS PRIVADOS DE AYUDA ==========

    /**
     * Calcular tasa de aprobación
     */
    private function calculatePassingRate($query)
    {
        $total = $query->count();
        if ($total === 0) return 0;

        $passing = $query->where('score', '>=', 6)->count();
        return round(($passing / $total) * 100, 2);
    }

    /**
     * Obtener los mejores estudiantes
     */
    private function getTopPerformers($query)
    {
        return $query->select('user_id', DB::raw('AVG(score) as avg_score'))
            ->groupBy('user_id')
            ->orderBy('avg_score', 'desc')
            ->limit(5)
            ->with('user')
            ->get();
    }

    /**
     * Obtener áreas de dificultad
     */
    private function getDifficultyAreas($query)
    {
        // Agrupar por tipo de evaluación
        return EvaluationResult::whereIn('id', $query->pluck('id'))
            ->join('evaluations', 'evaluation_results.evaluation_id', '=', 'evaluations.id')
            ->select('evaluations.type', DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->groupBy('evaluations.type')
            ->get();
    }

    /**
     * Obtener rango de fechas según período
     */
    private function getPeriodDateRange($period)
    {
        $now = now();
        
        switch ($period) {
            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
            case 'month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            case 'quarter':
                return [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()];
            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            default:
                return [$now->copy()->subDays(30), $now];
        }
    }

    /**
     * Analizar fortalezas y debilidades del estudiante
     */
    private function analyzeStrengths($evaluationResults)
    {
        if ($evaluationResults->isEmpty()) {
            return [
                'strengths' => [],
                'weaknesses' => [],
                'recommendations' => ['Completa más evaluaciones para obtener un análisis detallado']
            ];
        }

        // Agrupar por tipo de evaluación
        $byType = $evaluationResults->groupBy('evaluation.type');
        
        $analysis = [];
        foreach ($byType as $type => $results) {
            $avgScore = $results->avg('score');
            $analysis[$type] = [
                'average' => $avgScore,
                'count' => $results->count(),
                'status' => $avgScore >= 7 ? 'strength' : ($avgScore >= 5 ? 'neutral' : 'weakness')
            ];
        }

        $strengths = [];
        $weaknesses = [];
        $recommendations = [];

        foreach ($analysis as $type => $data) {
            if ($data['status'] === 'strength') {
                $strengths[] = $type;
            } elseif ($data['status'] === 'weakness') {
                $weaknesses[] = $type;
            }
        }

        // Generar recomendaciones
        if (!empty($weaknesses)) {
            $recommendations[] = 'Fortalecer áreas débiles: ' . implode(', ', $weaknesses);
        }
        if (!empty($strengths)) {
            $recommendations[] = 'Mantener fortalezas en: ' . implode(', ', $strengths);
        }
        if (empty($recommendations)) {
            $recommendations[] = 'Continúa con tu buen desempeño. ¡Sigue así!';
        }

        return [
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'recommendations' => $recommendations
        ];
    }
}