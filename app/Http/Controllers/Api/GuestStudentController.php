<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestStudentController extends Controller
{
    private const CAPTCHA_TTL_SECONDS = 300;
    private const CAPTCHA_MAX_ATTEMPTS = 5;

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dni' => 'required|string|size:8|digits',
            'captcha_token' => 'required|string',
            'captcha_answer' => 'required|string',
        ]);

        $decoded = $this->decodeCaptchaToken($validated['captcha_token']);
        if (!$decoded) {
            return response()->json([
                'success' => false,
                'message' => 'Captcha inválido o expirado. Intente nuevamente.',
            ], 422);
        }

        if (time() > $decoded['expires_at']) {
            return response()->json([
                'success' => false,
                'message' => 'Captcha expirado. Intente nuevamente.',
            ], 422);
        }

        $sessionCaptcha = $decoded['code'];
        if (strtoupper($validated['captcha_answer']) !== strtoupper($sessionCaptcha)) {
            $attempts = (int) session('captcha_attempts', 0) + 1;
            session(['captcha_attempts' => $attempts]);
            if ($attempts >= self::CAPTCHA_MAX_ATTEMPTS) {
                session()->forget('captcha_attempts');
                session()->forget('captcha_code');
                return response()->json([
                    'success' => false,
                    'message' => 'Demasiados intentos. Solicite un nuevo captcha.',
                ], 429);
            }
            return response()->json([
                'success' => false,
                'message' => 'Captcha inválido. Intente nuevamente.',
            ], 422);
        }

        session()->forget('captcha_attempts');
        session()->forget('captcha_code');

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

        $expiresAt = time() + self::CAPTCHA_TTL_SECONDS;
        session(['captcha_code' => $captcha, 'captcha_attempts' => 0]);
        $token = Crypt::encryptString(json_encode([
            'code' => $captcha,
            'expires_at' => $expiresAt,
        ]));

        return response()->json([
            'success' => true,
            'captcha_token' => $token,
            'captcha_image' => $this->renderSvg($captcha),
            'captcha_image_url' => null,
            'expires_in' => self::CAPTCHA_TTL_SECONDS,
        ]);
    }

    private function decodeCaptchaToken(?string $token): ?array
    {
        if (!$token) {
            return null;
        }
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($payload) || empty($payload['code']) || empty($payload['expires_at'])) {
            return null;
        }
        return $payload;
    }

    private function renderSvg(string $code): string
    {
        $width = 160;
        $height = 50;
        $lines = [];
        $text = '';

        for ($i = 0; $i < 5; $i++) {
            $lines[] = sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#cbd5e1" stroke-width="1.5"/>',
                random_int(5, $width - 5),
                random_int(5, $height),
                random_int(5, $width - 5),
                random_int(5, $height)
            );
        }

        foreach (str_split($code) as $i => $char) {
            $x = 18 + ($i * 24);
            $y = random_int(30, 38);
            $rot = random_int(-18, 18);
            $text .= sprintf(
                '<text x="%d" y="%d" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="#1e293b" transform="rotate(%d %d %d)">%s</text>',
                $x,
                $y,
                $rot,
                $x,
                $y,
                htmlspecialchars($char, ENT_QUOTES, 'UTF-8')
            );
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="captcha">%s%s</svg>',
            $width,
            $height,
            $width,
            $height,
            implode('', $lines),
            $text
        );

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
