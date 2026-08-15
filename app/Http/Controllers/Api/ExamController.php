<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamAttempt;
use App\Models\Notification;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $query = Exam::with(['teacher', 'questions']);

        if (Auth::user()->isTeacher() || Auth::user()->isAdmin()) {
            if (!Auth::user()->isAdmin()) {
                $query->where('teacher_id', Auth::id());
            }
        } elseif (Auth::user()->isStudent()) {
            $query->where('is_active', true);
        } elseif (Auth::user()->isParent()) {
            $query->where('is_active', true);
        }

        if ($request->has('is_active') && $request->is_active !== null) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('unit')) {
            $query->where('unit', $request->unit);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', $search)
                    ->orWhere('description', 'LIKE', $search);
            });
        }

        if (Auth::user()->isStudent() || Auth::user()->isParent()) {
            foreach ($query->get() as $exam) {
                foreach ($exam->questions as $question) {
                    $question->makeHidden(['correct_answer']);
                }
                if (Auth::user()->isStudent()) {
                    $attempt = ExamAttempt::where('student_id', Auth::id())
                        ->where('exam_id', $exam->id)
                        ->first();
                    $exam->user_attempt = $attempt;
                }
            }
        }

        $exams = $query->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->per_page ?? 15), 50));

        return response()->json([
            'success' => true,
            'data' => $exams
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:100',
            'difficulty' => 'nullable|in:basic,intermediate,advanced',
            'time_limit' => 'nullable|integer|min:1|max:999',
            'max_attempts' => 'nullable|integer|min:1|max:10',
            'auto_correct' => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
            'questions' => 'required|array|min:1',
            'questions.*.type' => 'required|in:multiple_choice,true_false,open_ended',
            'questions.*.question_text' => 'required|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.correct_answer' => 'required|string',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.points' => 'nullable|integer|min:1|max:100',
            'questions.*.order' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();

        try {
            $totalPoints = 0;
            foreach ($validated['questions'] as $q) {
                $totalPoints += $q['points'] ?? 1;
            }

            $exam = Exam::create([
                'id' => Str::uuid(),
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'teacher_id' => Auth::id(),
                'unit' => $validated['unit'] ?? null,
                'difficulty' => $validated['difficulty'] ?? 'basic',
                'time_limit' => $validated['time_limit'] ?? null,
                'max_attempts' => $validated['max_attempts'] ?? 1,
                'auto_correct' => $validated['auto_correct'] ?? true,
                'randomize_questions' => $validated['randomize_questions'] ?? false,
                'is_active' => false,
                'is_published' => false,
                'total_questions' => count($validated['questions']),
                'total_points' => $totalPoints,
            ]);

            foreach ($validated['questions'] as $index => $q) {
                ExamQuestion::create([
                    'id' => Str::uuid(),
                    'exam_id' => $exam->id,
                    'type' => $q['type'],
                    'question_text' => $q['question_text'],
                    'options' => $q['options'] ?? null,
                    'correct_answer' => $q['correct_answer'],
                    'explanation' => $q['explanation'] ?? null,
                    'points' => $q['points'] ?? 1,
                    'order' => $q['order'] ?? $index,
                ]);
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'exam.store',
                'auditable_type' => Exam::class,
                'auditable_id' => $exam->id,
                'new_values' => $exam->only('id', 'title', 'teacher_id', 'unit', 'difficulty'),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'method' => request()->method(),
                'path' => request()->path(),
                'platform' => request()->header('X-Platform', 'test'),
                'status_code' => 201,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Examen creado como borrador',
                'data' => $exam->load(['teacher', 'questions'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el examen'
            ], 500);
        }
    }

    public function show($id)
    {
        $exam = Exam::with(['teacher', 'questions'])->findOrFail($id);

        if (Auth::user()->isStudent() || Auth::user()->isParent()) {
            if (!$exam->is_active && Auth::user()->isStudent()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Examen no disponible'
                ], 403);
            }

            if (Auth::user()->isStudent()) {
                $attempt = ExamAttempt::where('student_id', Auth::id())
                    ->where('exam_id', $id)
                    ->first();
                $exam->user_attempt = $attempt;
            }

            foreach ($exam->questions as $question) {
                $question->makeHidden(['correct_answer']);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $exam
        ]);
    }

    public function update(Request $request, $id)
    {
        $exam = Exam::findOrFail($id);

        if ((string) $exam->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para editar este examen'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:100',
            'difficulty' => 'sometimes|in:basic,intermediate,advanced',
            'time_limit' => 'nullable|integer|min:1|max:999',
            'max_attempts' => 'nullable|integer|min:1|max:10',
            'auto_correct' => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
            'questions' => 'sometimes|array|min:1',
            'questions.*.type' => 'required_with:questions|in:multiple_choice,true_false,open_ended',
            'questions.*.question_text' => 'required_with:questions|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.correct_answer' => 'required_with:questions|string',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.points' => 'nullable|integer|min:1|max:100',
            'questions.*.order' => 'nullable|integer|min:0',
            'questions.*.id' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $examFields = collect($validated)->except('questions')->filter()->toArray();
            if (!empty($examFields)) {
                $exam->update($examFields);
            }

            if (isset($validated['questions'])) {
                $exam->questions()->delete();

                $totalPoints = 0;
                foreach ($validated['questions'] as $index => $q) {
                    $totalPoints += $q['points'] ?? 1;
                    ExamQuestion::create([
                        'id' => $q['id'] ?? Str::uuid(),
                        'exam_id' => $exam->id,
                        'type' => $q['type'],
                        'question_text' => $q['question_text'],
                        'options' => $q['options'] ?? null,
                        'correct_answer' => $q['correct_answer'],
                        'explanation' => $q['explanation'] ?? null,
                        'points' => $q['points'] ?? 1,
                        'order' => $q['order'] ?? $index,
                    ]);
                }

                $exam->update([
                    'total_questions' => count($validated['questions']),
                    'total_points' => $totalPoints,
                ]);
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'exam.update',
                'auditable_type' => Exam::class,
                'auditable_id' => $exam->id,
                'new_values' => $validated,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'method' => request()->method(),
                'path' => request()->path(),
                'platform' => request()->header('X-Platform', 'test'),
                'status_code' => 200,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Examen actualizado',
                'data' => $exam->load(['teacher', 'questions'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el examen'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $exam = Exam::findOrFail($id);

        if ((string) $exam->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para eliminar este examen'
            ], 403);
        }

        if ($exam->attempts()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un examen que ya tiene intentos registrados'
            ], 400);
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'exam.destroy',
            'auditable_type' => Exam::class,
            'auditable_id' => $exam->id,
            'old_values' => $exam->only('id', 'title', 'teacher_id'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'method' => request()->method(),
            'path' => request()->path(),
            'platform' => request()->header('X-Platform', 'test'),
            'status_code' => 200,
        ]);

        $exam->delete();

        return response()->json([
            'success' => true,
            'message' => 'Examen eliminado'
        ]);
    }

    public function activate($id)
    {
        $exam = Exam::findOrFail($id);

        if ((string) $exam->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para activar este examen'
            ], 403);
        }

        if ($exam->questions()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'El examen debe tener al menos una pregunta para activarlo'
            ], 400);
        }

        $exam->update([
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Examen activado y publicado',
            'data' => $exam
        ]);
    }

    public function deactivate($id)
    {
        $exam = Exam::findOrFail($id);

        if ((string) $exam->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para desactivar este examen'
            ], 403);
        }

        $exam->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Examen desactivado',
            'data' => $exam
        ]);
    }

    public function startAttempt($id)
    {
        $exam = Exam::findOrFail($id);

        if (!$exam->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Este examen no está disponible'
            ], 403);
        }

        $studentId = Auth::id();

        $existingAttempt = ExamAttempt::where('exam_id', $id)
            ->where('student_id', $studentId)
            ->where('status', ExamAttempt::STATUS_IN_PROGRESS)
            ->first();

        if ($existingAttempt) {
            return response()->json([
                'success' => true,
                'message' => 'Retomando intento en curso',
                'data' => [
                    'attempt' => $existingAttempt,
                    'questions' => $exam->questions()->orderBy('order')->get()->map(function ($q) {
                        $q->makeHidden(['correct_answer']);
                        return $q;
                    }),
                ]
            ]);
        }

        $completedAttempts = ExamAttempt::where('exam_id', $id)
            ->where('student_id', $studentId)
            ->where('status', ExamAttempt::STATUS_COMPLETED)
            ->count();

        if ($completedAttempts >= $exam->max_attempts) {
            return response()->json([
                'success' => false,
                'message' => 'Ha alcanzado el número máximo de intentos para este examen'
            ], 403);
        }

        $attempt = ExamAttempt::create([
            'id' => Str::uuid(),
            'exam_id' => $exam->id,
            'student_id' => $studentId,
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
            'total_points' => $exam->total_points,
            'started_at' => now(),
        ]);

        $questions = $exam->questions()->orderBy('order')->get()->map(function ($q) {
            $q->makeHidden(['correct_answer']);
            return $q;
        });

        if ($exam->randomize_questions) {
            $questions = $questions->shuffle()->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Intento iniciado',
            'data' => [
                'attempt' => $attempt,
                'questions' => $questions,
            ]
        ], 201);
    }

    public function submitAttempt(Request $request, $attemptId)
    {
        $attempt = ExamAttempt::findOrFail($attemptId);

        if ((string) $attempt->student_id !== (string) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para enviar este intento'
            ], 403);
        }

        if ($attempt->status !== ExamAttempt::STATUS_IN_PROGRESS) {
            return response()->json([
                'success' => false,
                'message' => 'Este intento ya fue enviado o no está en curso'
            ], 400);
        }

        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|string|exists:exam_questions,id',
            'answers.*.answer' => 'required|string',
            'time_spent' => 'nullable|integer|min:0',
        ]);

        $exam = $attempt->exam;
        $questions = $exam->questions()->get()->keyBy('id');

        DB::beginTransaction();

        try {
            $correctCount = 0;
            $earnedPoints = 0;
            $answersResult = [];

            foreach ($validated['answers'] as $answerData) {
                $question = $questions->get($answerData['question_id']);
                if (!$question) {
                    continue;
                }

                $isCorrect = $this->normalizeAnswer($answerData['answer']) === $this->normalizeAnswer($question->correct_answer);
                $pointsEarned = $isCorrect ? $question->points : 0;

                if ($isCorrect) {
                    $correctCount++;
                    $earnedPoints += $pointsEarned;
                }

                $answersResult[] = [
                    'question_id' => $question->id,
                    'answer' => $answerData['answer'],
                    'is_correct' => $isCorrect,
                    'points_earned' => $pointsEarned,
                    'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation,
                ];
            }

            $score = $exam->total_points > 0
                ? round(($earnedPoints / $exam->total_points) * 20)
                : 0;

            $attempt->update([
                'status' => ExamAttempt::STATUS_COMPLETED,
                'score' => $score,
                'answers' => $answersResult,
                'time_spent' => $validated['time_spent'] ?? 0,
                'completed_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'exam_attempt.submit',
                'auditable_type' => ExamAttempt::class,
                'auditable_id' => $attempt->id,
                'new_values' => ['score' => $score, 'status' => ExamAttempt::STATUS_COMPLETED],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'method' => request()->method(),
                'path' => request()->path(),
                'platform' => request()->header('X-Platform', 'test'),
                'status_code' => 200,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Examen enviado correctamente',
                'data' => [
                    'attempt' => $attempt->fresh(),
                    'score' => $score,
                    'correct_answers' => $correctCount,
                    'total_questions' => $exam->total_questions,
                    'earned_points' => $earnedPoints,
                    'total_points' => $exam->total_points,
                    'answers' => $answersResult,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el examen'
            ], 500);
        }
    }

    public function reportCheating(Request $request, $attemptId)
    {
        $attempt = ExamAttempt::findOrFail($attemptId);

        if ((string) $attempt->student_id !== (string) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para realizar esta acción.',
            ], 403);
        }

        $validated = $request->validate([
            'event' => 'required|string|in:tab_switch,window_blur,copy_attempt,paste_attempt,fullscreen_exit,screenshot_attempt',
            'detail' => 'nullable|string',
        ]);

        $cheatLog = $attempt->cheat_log ?? [];
        $cheatLog[] = [
            'event' => $validated['event'],
            'detail' => $validated['detail'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ];

        $newTabCount = $attempt->tab_switch_count + 1;

        $updateData = [
            'cheat_log' => $cheatLog,
            'tab_switch_count' => $newTabCount,
        ];

        if ($newTabCount >= 3 && $attempt->status === ExamAttempt::STATUS_IN_PROGRESS) {
            $updateData['status'] = ExamAttempt::STATUS_CHEATING_DETECTED;

            Notification::create([
                'id' => Str::uuid(),
                'user_id' => $attempt->exam->teacher_id,
                'title' => 'Actividad sospechosa detectada',
                'message' => "El estudiante #" . substr($attempt->student_id, 0, 8) . " ha sido marcado por actividad sospechosa en el examen \"{$attempt->exam->title}\" ({$newTabCount} cambios de pestaña).",
                'type' => Notification::TYPE_WARNING,
                'is_read' => false,
                'link' => "/exams/{$attempt->exam_id}/attempts/{$attempt->id}",
            ]);
        }

        $attempt->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Evento registrado',
            'data' => [
                'tab_switch_count' => $newTabCount,
                'status' => $attempt->fresh()->status,
            ]
        ]);
    }

    public function getExamStats($id)
    {
        $exam = Exam::findOrFail($id);

        if ((string) $exam->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para ver las estadísticas de este examen'
            ], 403);
        }

        $attempts = ExamAttempt::where('exam_id', $id)->get();
        $completedAttempts = $attempts->where('status', ExamAttempt::STATUS_COMPLETED);
        $cheatingIncidents = $attempts->where('status', ExamAttempt::STATUS_CHEATING_DETECTED);

        $scores = $completedAttempts->pluck('score')->filter()->values();
        $totalScores = $scores->count();

        $distribution = [
            '0-6' => 0,
            '7-11' => 0,
            '12-14' => 0,
            '15-17' => 0,
            '18-20' => 0,
        ];

        foreach ($scores as $score) {
            if ($score < 7) $distribution['0-6']++;
            elseif ($score < 12) $distribution['7-11']++;
            elseif ($score < 15) $distribution['12-14']++;
            elseif ($score < 18) $distribution['15-17']++;
            else $distribution['18-20']++;
        }

        $passingCount = $scores->filter(fn($s) => $s >= 12)->count();

        $stats = [
            'total_attempts' => $attempts->count(),
            'completed_attempts' => $completedAttempts->count(),
            'in_progress_attempts' => $attempts->where('status', ExamAttempt::STATUS_IN_PROGRESS)->count(),
            'average_score' => $totalScores > 0 ? round($scores->sum() / $totalScores, 2) : 0,
            'max_score' => $totalScores > 0 ? $scores->max() : 0,
            'min_score' => $totalScores > 0 ? $scores->min() : 0,
            'pass_rate' => $totalScores > 0 ? round(($passingCount / $totalScores) * 100, 2) : 0,
            'cheating_incidents' => $cheatingIncidents->count(),
            'total_cheat_events' => $attempts->sum('tab_switch_count'),
            'score_distribution' => $distribution,
            'average_time_spent' => $completedAttempts->count() > 0
                ? round($completedAttempts->avg('time_spent'))
                : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    private function normalizeAnswer(string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['verdadero', 'true', 'v'])) {
            return 'true';
        }
        if (in_array($value, ['falso', 'false', 'f'])) {
            return 'false';
        }
        $value = preg_replace('/\s+/', '', $value);
        $value = str_replace(['×', '÷', '²', '³'], ['*', '/', '^2', '^3'], $value);
        $value = preg_replace('/\.0$/', '', $value);
        $value = preg_replace('/^0+(\d)/', '$1', $value);

        return $value;
    }
}
