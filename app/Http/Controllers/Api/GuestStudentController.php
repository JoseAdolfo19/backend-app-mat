<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestStudentController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dni' => 'required|string|size:8|digits',
            'captcha_token' => 'required|string',
            'captcha_answer' => 'required|string',
        ]);

        $sessionCaptcha = session('captcha_code');
        if (!$sessionCaptcha || strtoupper($validated['captcha_answer']) !== strtoupper($sessionCaptcha)) {
            return response()->json([
                'success' => false,
                'message' => 'Captcha inválido. Intente nuevamente.',
            ], 422);
        }

        $student = User::where('dni', $validated['dni'])
            ->whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Estudiante no encontrado con ese DNI.',
            ], 404);
        }

        $profile = $student->studentProfile;

        $totalLessonsCompleted = $profile->total_lessons_completed ?? 0;
        $averageScore = $profile->average_score ?? 0;
        $currentStreak = $profile->current_streak ?? 0;
        $badges = $profile->badges ?? [];

        $evaluationResults = DB::table('evaluation_results')
            ->join('evaluations', 'evaluations.id', '=', 'evaluation_results.evaluation_id')
            ->where('evaluation_results.user_id', $student->id)
            ->where('evaluation_results.status', 'completed')
            ->orderByDesc('evaluation_results.completed_at')
            ->limit(10)
            ->select(
                'evaluations.title',
                'evaluation_results.score',
                'evaluation_results.completed_at as date'
            )
            ->get();

        $lessonProgressSummary = DB::table('lesson_progress')
            ->where('user_id', $student->id)
            ->selectRaw("
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) as not_started
            ")
            ->first();

        $gradesByArea = DB::table('evaluation_results')
            ->join('evaluations', 'evaluations.id', '=', 'evaluation_results.evaluation_id')
            ->join('lessons', 'lessons.id', '=', 'evaluations.lesson_id')
            ->where('evaluation_results.user_id', $student->id)
            ->where('evaluation_results.status', 'completed')
            ->select(
                'lessons.unit as area_name',
                DB::raw('AVG(evaluation_results.score) as average_score')
            )
            ->groupBy('lessons.unit')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'student' => [
                    'name' => $student->full_name,
                    'grade' => $student->grade,
                    'institution' => $student->institution,
                ],
                'average_score' => $averageScore,
                'total_lessons_completed' => $totalLessonsCompleted,
                'current_streak' => $currentStreak,
                'badges' => $badges,
                'evaluation_results' => $evaluationResults,
                'lesson_progress_summary' => [
                    'completed' => $lessonProgressSummary->completed ?? 0,
                    'in_progress' => $lessonProgressSummary->in_progress ?? 0,
                    'not_started' => $lessonProgressSummary->not_started ?? 0,
                ],
                'grades_by_area' => $gradesByArea,
            ],
        ]);
    }

    public function generateCaptcha(): JsonResponse
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $captcha = '';
        for ($i = 0; $i < 6; $i++) {
            $captcha .= $chars[random_int(0, strlen($chars) - 1)];
        }

        session(['captcha_code' => $captcha]);

        return response()->json([
            'success' => true,
            'captcha_code' => $captcha,
            'captcha_image_url' => null,
        ]);
    }
}
