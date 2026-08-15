<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $children = $user->children()->with('studentProfile')->get();

        return response()->json([
            'children' => $children
        ]);
    }

    public function childProgress($studentId)
    {
        $user = Auth::user();

        if (!$user->children()->where('student_id', $studentId)->exists()) {
            return response()->json(['message' => 'Student not linked to this parent'], 404);
        }

        $student = User::with(['studentProfile', 'role'])->findOrFail($studentId);

        $lessonsInProgress = LessonProgress::where('user_id', $studentId)
            ->whereIn('status', ['not_started', 'in_progress'])
            ->with('lesson')
            ->get();

        $completedLessons = LessonProgress::where('user_id', $studentId)
            ->where('status', 'completed')
            ->with('lesson')
            ->orderBy('completed_at', 'desc')
            ->get();

        $recentEvaluations = EvaluationResult::where('user_id', $studentId)
            ->with('evaluation')
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get();

        $totalLessons = LessonProgress::where('user_id', $studentId)->count();
        $completedCount = LessonProgress::where('user_id', $studentId)
            ->where('status', 'completed')->count();

        $averageScore = EvaluationResult::where('user_id', $studentId)
            ->where('status', 'completed')->avg('score');

        return response()->json([
            'student' => $student,
            'stats' => [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedCount,
                'completion_rate' => $totalLessons > 0
                    ? round(($completedCount / $totalLessons) * 100, 1)
                    : 0,
                'average_score' => $averageScore ? round($averageScore, 1) : 0,
                'current_streak' => $student->studentProfile?->current_streak ?? 0,
                'total_time_spent' => $student->studentProfile?->total_time_spent ?? 0,
            ],
            'lessons_in_progress' => $lessonsInProgress,
            'completed_lessons' => $completedLessons,
            'recent_evaluations' => $recentEvaluations,
        ]);
    }

    public function childReport($studentId)
    {
        $user = Auth::user();

        if (!$user->children()->where('student_id', $studentId)->exists()) {
            return response()->json(['message' => 'Student not linked to this parent'], 404);
        }

        $student = User::with(['studentProfile', 'role'])->findOrFail($studentId);

        $evaluationResults = EvaluationResult::where('user_id', $studentId)
            ->where('status', 'completed')
            ->with('evaluation')
            ->get();

        $lessonProgress = LessonProgress::where('user_id', $studentId)
            ->with('lesson')
            ->get();

        $totalEvaluations = $evaluationResults->count();
        $passedEvaluations = $evaluationResults->where('score', '>=', 12)->count();
        $averageScore = $evaluationResults->avg('score');
        $highestScore = $evaluationResults->max('score');
        $lowestScore = $evaluationResults->min('score');

        $subjectPerformance = $evaluationResults->groupBy('evaluation.topic')->map(function ($results) {
            return [
                'topic' => $results->first()->evaluation->topic ?? 'Sin tema',
                'average_score' => round($results->avg('score'), 1),
                'total_attempts' => $results->count(),
                'best_score' => $results->max('score'),
            ];
        })->values();

        $completedLessons = $lessonProgress->where('status', 'completed')->count();
        $totalLessons = $lessonProgress->count();
        $totalTimeSpent = $lessonProgress->sum('time_spent');

        return response()->json([
            'student' => $student,
            'summary' => [
                'total_evaluations' => $totalEvaluations,
                'passed_evaluations' => $passedEvaluations,
                'pass_rate' => $totalEvaluations > 0
                    ? round(($passedEvaluations / $totalEvaluations) * 100, 1)
                    : 0,
                'average_score' => $averageScore ? round($averageScore, 1) : 0,
                'highest_score' => $highestScore,
                'lowest_score' => $lowestScore,
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
                'lesson_completion_rate' => $totalLessons > 0
                    ? round(($completedLessons / $totalLessons) * 100, 1)
                    : 0,
                'total_time_spent_minutes' => round($totalTimeSpent / 60),
            ],
            'subject_performance' => $subjectPerformance,
            'evaluation_details' => $evaluationResults->map(function ($result) {
                return [
                    'evaluation_title' => $result->evaluation->title ?? 'Sin título',
                    'score' => $result->score,
                    'max_score' => $result->max_score,
                    'correct_answers' => $result->correct_answers,
                    'total_questions' => $result->total_questions,
                    'completed_at' => $result->completed_at,
                ];
            }),
        ]);
    }
}
