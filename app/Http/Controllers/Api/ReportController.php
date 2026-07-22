<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Lesson;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\LessonProgress;
use App\Exports\GradesExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Reporte de rendimiento general
     */
    public function performanceReport(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:week,month,quarter,year,current,last_month,last_quarter,all_time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date'
        ]);

        $query = EvaluationResult::query();

        // Filtros de fecha
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        } elseif ($request->has('period') && $request->period !== 'all_time') {
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

        return response()->json(['data' => $stats]);
    }

    /**
     * Reporte de calificaciones
     */
    public function gradesReport(Request $request)
    {
        $request->validate([
            'evaluation_id' => 'nullable|exists:evaluations,id',
            'student_id' => 'nullable|exists:users,id',
            'period' => 'nullable|in:week,month,quarter,year,current,last_month,last_quarter,all_time'
        ]);

        $query = $this->buildGradesQuery($request);

        $results = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        // Resumen estadístico (recalculado sin paginar)
        $summaryQuery = $this->buildGradesQuery($request);
        $summary = [
            'average' => $summaryQuery->avg('score'),
            'max' => $summaryQuery->max('score'),
            'min' => $summaryQuery->min('score'),
            'total' => $summaryQuery->count()
        ];

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'total' => $results->total(),
            ],
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

        $lessonProgress = LessonProgress::where('user_id', $userId)
            ->with('lesson')
            ->get();

        $evaluationResults = EvaluationResult::where('user_id', $userId)
            ->with('evaluation')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

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
        $rows = $this->buildGradesQuery($request)
            ->orderBy('created_at', 'desc')
            ->get();

        $summary = [
            'average' => $rows->avg('score'),
            'max' => $rows->max('score'),
            'min' => $rows->min('score'),
            'total' => $rows->count()
        ];

        $pdf = Pdf::loadView('reports.report-pdf', [
            'rows' => $rows,
            'summary' => $summary,
            'period' => $request->period ?? 'current'
        ]);

        return $pdf->download('reporte-rendimiento-' . ($request->period ?? 'current') . '.pdf');
    }

    /**
     * Exportar reporte en Excel
     */
    public function exportExcel(Request $request)
    {
        $rows = $this->buildGradesQuery($request)
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'reporte-rendimiento-' . ($request->period ?? 'current') . '.xlsx';

        return Excel::download(new GradesExport($rows), $filename);
    }

    // ========== MÉTODOS PRIVADOS DE AYUDA ==========

    /**
     * Construye el query base de calificaciones, aplicando los mismos filtros
     * que gradesReport/exportPDF/exportExcel usan en común (evita duplicar lógica).
     */
    private function buildGradesQuery(Request $request)
    {
        $query = EvaluationResult::with(['user', 'evaluation'])
            ->where('status', 'completed');

        if ($request->filled('evaluation_id')) {
            $query->where('evaluation_id', $request->evaluation_id);
        }

        if ($request->filled('student_id')) {
            $query->where('user_id', $request->student_id);
        }

        if ($request->filled('period') && $request->period !== 'all_time') {
            $query->whereBetween('created_at', $this->getPeriodDateRange($request->period));
        }

        if (Auth::user()->isTeacher()) {
            $evaluationIds = Evaluation::where('teacher_id', Auth::id())->pluck('id');
            $query->whereIn('evaluation_id', $evaluationIds);
        }

        return $query;
    }

    private function calculatePassingRate($query)
    {
        $total = $query->count();
        if ($total === 0) return 0;

        $passing = $query->where('score', '>=', 6)->count();
        return round(($passing / $total) * 100, 2);
    }

    private function getTopPerformers($query)
    {
        return $query->select('user_id', DB::raw('AVG(score) as avg_score'))
            ->groupBy('user_id')
            ->orderBy('avg_score', 'desc')
            ->limit(5)
            ->with('user')
            ->get();
    }

    private function getDifficultyAreas($query)
    {
        return EvaluationResult::whereIn('id', $query->pluck('id'))
            ->join('evaluations', 'evaluation_results.evaluation_id', '=', 'evaluations.id')
            ->select('evaluations.type', DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->groupBy('evaluations.type')
            ->get();
    }

    private function getPeriodDateRange($period)
    {
        $now = now();

        switch ($period) {
            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
            case 'month':
            case 'last_month':
                return [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()];
            case 'quarter':
            case 'last_quarter':
                return [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()];
            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            case 'current':
            default:
                return [$now->copy()->subDays(30), $now];
        }
    }

    private function analyzeStrengths($evaluationResults)
    {
        if ($evaluationResults->isEmpty()) {
            return [
                'strengths' => [],
                'weaknesses' => [],
                'recommendations' => ['Completa más evaluaciones para obtener un análisis detallado']
            ];
        }

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