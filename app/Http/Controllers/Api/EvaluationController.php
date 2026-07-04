<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Question;
use App\Models\EvaluationResult;
use App\Models\StudentAnswer;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    /**
     * Listar evaluaciones con filtros
     */
    public function index(Request $request)
    {
        $query = Evaluation::with(['teacher', 'lesson', 'results']);

        // Filtros
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('difficulty') && $request->difficulty) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->has('lesson_id') && $request->lesson_id) {
            $query->where('lesson_id', $request->lesson_id);
        }

        if ($request->has('search') && $request->search) {
            $query->where('title', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('description', 'LIKE', '%' . $request->search . '%');
        }

        if ($request->has('status') && $request->status) {
            if ($request->status === 'published') {
                $query->where('is_published', true);
            } elseif ($request->status === 'draft') {
                $query->where('is_published', false);
            }
        }

        // Si es estudiante, solo mostrar evaluaciones publicadas
        if (Auth::user()->isStudent()) {
            $query->where('is_published', true);
            
            // Mostrar solo evaluaciones disponibles (fecha de entrega no pasada)
            if ($request->has('available') && $request->available) {
                $query->where(function($q) {
                    $q->where('due_date', '>=', now())
                      ->orWhereNull('due_date');
                });
            }
        }

        // Si es docente, mostrar sus evaluaciones
        if (Auth::user()->isTeacher()) {
            $query->where('teacher_id', Auth::id());
        }

        $evaluations = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        // Agregar información de resultados para estudiantes
        if (Auth::user()->isStudent()) {
            foreach ($evaluations as $evaluation) {
                $result = EvaluationResult::where('user_id', Auth::id())
                    ->where('evaluation_id', $evaluation->id)
                    ->first();
                $evaluation->user_result = $result;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $evaluations
        ]);
    }

    /**
     * Mostrar una evaluación específica
     */
    public function show($id)
    {
        $evaluation = Evaluation::with(['teacher', 'lesson', 'questions'])
            ->findOrFail($id);

        // Verificar permisos
        if (Auth::user()->isStudent()) {
            if (!$evaluation->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta evaluación no está disponible'
                ], 403);
            }

            // Verificar si el estudiante ya la completó
            $result = EvaluationResult::where('user_id', Auth::id())
                ->where('evaluation_id', $id)
                ->first();

            if ($result && $result->status === 'completed') {
                $evaluation->already_completed = true;
                $evaluation->result = $result;
            }

            // Ocultar respuestas correctas
            foreach ($evaluation->questions as $question) {
                $question->makeHidden(['correct_answer']);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $evaluation
        ]);
    }

    /**
     * Crear una nueva evaluación
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'lesson_id' => 'nullable|exists:lessons,id',
            'type' => 'required|in:exam,quiz,homework,practice',
            'difficulty' => 'required|in:basic,intermediate,advanced',
            'time_limit' => 'nullable|integer|min:1|max:999',
            'due_date' => 'nullable|date|after:now',
            'auto_correct' => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
            'max_attempts' => 'nullable|integer|min:1|max:10'
        ]);

        // Verificar que la lección exista y pertenezca al docente
        if ($request->has('lesson_id')) {
            $lesson = Lesson::find($request->lesson_id);
            if ($lesson && $lesson->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para asociar esta evaluación a la lección'
                ], 403);
            }
        }

        $evaluation = Evaluation::create([
            'id' => Str::uuid(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'teacher_id' => Auth::id(),
            'lesson_id' => $validated['lesson_id'] ?? null,
            'type' => $validated['type'],
            'difficulty' => $validated['difficulty'],
            'time_limit' => $validated['time_limit'] ?? 30,
            'due_date' => $validated['due_date'] ?? null,
            'auto_correct' => $validated['auto_correct'] ?? true,
            'randomize_questions' => $validated['randomize_questions'] ?? false,
            'max_attempts' => $validated['max_attempts'] ?? 1,
            'is_published' => false,
            'total_questions' => 0,
            'total_points' => 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Evaluación creada exitosamente',
            'data' => $evaluation->load('teacher')
        ], 201);
    }

    /**
     * Actualizar una evaluación
     */
    public function update(Request $request, $id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ($evaluation->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para editar esta evaluación'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'lesson_id' => 'nullable|exists:lessons,id',
            'type' => 'sometimes|in:exam,quiz,homework,practice',
            'difficulty' => 'sometimes|in:basic,intermediate,advanced',
            'time_limit' => 'nullable|integer|min:1|max:999',
            'due_date' => 'nullable|date|after:now',
            'auto_correct' => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
            'max_attempts' => 'nullable|integer|min:1|max:10',
            'is_published' => 'nullable|boolean'
        ]);

        $evaluation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Evaluación actualizada exitosamente',
            'data' => $evaluation->load('teacher')
        ]);
    }

    /**
     * Eliminar una evaluación
     */
    public function destroy($id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ($evaluation->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar esta evaluación'
            ], 403);
        }

        // Verificar si tiene resultados
        if ($evaluation->results()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la evaluación porque tiene resultados asociados'
            ], 400);
        }

        $evaluation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Evaluación eliminada exitosamente'
        ]);
    }

    /**
     * Publicar una evaluación
     */
    public function publish($id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ($evaluation->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para publicar esta evaluación'
            ], 403);
        }

        // Verificar que tenga preguntas
        if ($evaluation->questions()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'La evaluación debe tener al menos una pregunta para ser publicada'
            ], 400);
        }

        $evaluation->update([
            'is_published' => true,
            'published_at' => now()
        ]);

        // Crear notificación para estudiantes
        // NotificationController::createBulkNotifications(...);

        return response()->json([
            'success' => true,
            'message' => 'Evaluación publicada exitosamente',
            'data' => $evaluation
        ]);
    }

    /**
     * Despublicar una evaluación
     */
    public function unpublish($id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ($evaluation->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para despublicar esta evaluación'
            ], 403);
        }

        $evaluation->update(['is_published' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Evaluación despublicada exitosamente'
        ]);
    }

    /**
     * Obtener preguntas de una evaluación
     */
    public function getQuestions($evaluationId)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        // Verificar permisos
        if (Auth::user()->isStudent() && !$evaluation->is_published) {
            return response()->json([
                'success' => false,
                'message' => 'Esta evaluación no está disponible'
            ], 403);
        }

        $questions = $evaluation->questions()
            ->orderBy('order', 'asc')
            ->get();

        // Si es estudiante, ocultar respuestas correctas
        if (Auth::user()->isStudent()) {
            $questions->makeHidden(['correct_answer']);
        }

        return response()->json([
            'success' => true,
            'data' => $questions
        ]);
    }

    /**
     * Agregar una pregunta a la evaluación
     */
    public function addQuestion(Request $request, $evaluationId)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        if ($evaluation->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para agregar preguntas'
            ], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:multiple_choice,fill_blank,drag_drop,formula',
            'question_text' => 'required|string',
            'options' => 'nullable|array',
            'options.*.label' => 'required|string',
            'options.*.value' => 'required|string',
            'correct_answer' => 'required|string',
            'explanation' => 'nullable|string',
            'points' => 'nullable|integer|min:1|max:100',
            'order' => 'nullable|integer|min:0'
        ]);

        // Validar opciones para preguntas de opción múltiple
        if ($validated['type'] === 'multiple_choice' && empty($validated['options'])) {
            return response()->json([
                'success' => false,
                'message' => 'Las preguntas de opción múltiple requieren opciones'
            ], 400);
        }

        $question = Question::create([
            'id' => Str::uuid(),
            'evaluation_id' => $evaluationId,
            'type' => $validated['type'],
            'question_text' => $validated['question_text'],
            'options' => $validated['options'] ?? null,
            'correct_answer' => $validated['correct_answer'],
            'explanation' => $validated['explanation'] ?? null,
            'points' => $validated['points'] ?? 1,
            'order' => $validated['order'] ?? 0
        ]);

        // Actualizar total de preguntas y puntos
        $this->updateEvaluationTotals($evaluationId);

        return response()->json([
            'success' => true,
            'message' => 'Pregunta agregada exitosamente',
            'data' => $question
        ], 201);
    }

    /**
     * Actualizar una pregunta
     */
    public function updateQuestion(Request $request, $questionId)
    {
        $question = Question::findOrFail($questionId);
        $evaluation = $question->evaluation;

        if ($evaluation->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para editar esta pregunta'
            ], 403);
        }

        $validated = $request->validate([
            'type' => 'sometimes|in:multiple_choice,fill_blank,drag_drop,formula',
            'question_text' => 'sometimes|string',
            'options' => 'nullable|array',
            'options.*.label' => 'required|string',
            'options.*.value' => 'required|string',
            'correct_answer' => 'sometimes|string',
            'explanation' => 'nullable|string',
            'points' => 'nullable|integer|min:1|max:100',
            'order' => 'nullable|integer|min:0'
        ]);

        $question->update($validated);

        // Actualizar total de preguntas y puntos
        $this->updateEvaluationTotals($evaluation->id);

        return response()->json([
            'success' => true,
            'message' => 'Pregunta actualizada exitosamente',
            'data' => $question
        ]);
    }

    /**
     * Eliminar una pregunta
     */
    public function deleteQuestion($questionId)
    {
        $question = Question::findOrFail($questionId);
        $evaluation = $question->evaluation;

        if ($evaluation->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar esta pregunta'
            ], 403);
        }

        $question->delete();

        // Actualizar total de preguntas y puntos
        $this->updateEvaluationTotals($evaluation->id);

        return response()->json([
            'success' => true,
            'message' => 'Pregunta eliminada exitosamente'
        ]);
    }

    /**
     * Enviar una evaluación (estudiante)
     */
    public function submit(Request $request, $evaluationId)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        if (!$evaluation->is_published) {
            return response()->json([
                'success' => false,
                'message' => 'Esta evaluación no está disponible'
            ], 403);
        }

        // Verificar fecha límite
        if ($evaluation->due_date && now() > $evaluation->due_date) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha límite para esta evaluación ha pasado'
            ], 403);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'required|string',
            'time_taken' => 'nullable|integer|min:0'
        ]);

        $user = Auth::user();

        // Verificar intentos
        $attemptsCount = EvaluationResult::where('user_id', $user->id)
            ->where('evaluation_id', $evaluationId)
            ->where('status', 'completed')
            ->count();

        if ($attemptsCount >= $evaluation->max_attempts) {
            return response()->json([
                'success' => false,
                'message' => 'Has alcanzado el número máximo de intentos permitidos'
            ], 403);
        }

        DB::beginTransaction();

        try {
            $questions = $evaluation->questions()->get();
            $totalQuestions = $questions->count();
            $correctAnswers = 0;
            $totalPoints = $questions->sum('points');
            $earnedPoints = 0;

            // Crear resultado
            $result = EvaluationResult::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'evaluation_id' => $evaluationId,
                'max_score' => $totalPoints,
                'total_questions' => $totalQuestions,
                'status' => EvaluationResult::STATUS_PENDING,
                'started_at' => now(),
                'attempt_number' => $attemptsCount + 1,
                'time_taken' => $validated['time_taken'] ?? 0
            ]);

            // Procesar respuestas
            foreach ($validated['answers'] as $answerData) {
                $question = $questions->firstWhere('id', $answerData['question_id']);
                
                if (!$question) {
                    continue;
                }

                $isCorrect = $this->checkAnswer($question, $answerData['answer']);
                $pointsEarned = $isCorrect ? $question->points : 0;

                if ($isCorrect) {
                    $correctAnswers++;
                    $earnedPoints += $pointsEarned;
                }

                StudentAnswer::create([
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'evaluation_result_id' => $result->id,
                    'question_id' => $question->id,
                    'answer' => $answerData['answer'],
                    'is_correct' => $isCorrect,
                    'points_earned' => $pointsEarned
                ]);
            }

            // Calcular puntaje (escala 0-10)
            $score = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 10 : 0;

            // Actualizar resultado
            $result->update([
                'score' => round($score, 2),
                'correct_answers' => $correctAnswers,
                'status' => EvaluationResult::STATUS_COMPLETED,
                'completed_at' => now()
            ]);

            // Actualizar perfil del estudiante
            $this->updateStudentProfile($user->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Evaluación enviada exitosamente',
                'data' => [
                    'result' => $result->load('studentAnswers.question'),
                    'score' => $result->score,
                    'correct_answers' => $correctAnswers,
                    'total_questions' => $totalQuestions
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la evaluación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resultados de una evaluación
     */
    public function getResults($evaluationId)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        // Verificar permisos
        if (Auth::user()->isStudent()) {
            $results = EvaluationResult::where('user_id', Auth::id())
                ->where('evaluation_id', $evaluationId)
                ->with('studentAnswers.question')
                ->get();
        } else {
            // Docente o admin - verificar acceso
            if ($evaluation->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para ver estos resultados'
                ], 403);
            }

            $results = EvaluationResult::where('evaluation_id', $evaluationId)
                ->with(['user', 'studentAnswers.question'])
                ->get();

            // Estadísticas
            $stats = [
                'total_submissions' => $results->count(),
                'average_score' => $results->avg('score') ?? 0,
                'max_score' => $results->max('score') ?? 0,
                'min_score' => $results->min('score') ?? 0,
                'passing_rate' => $this->calculatePassingRate($results)
            ];

            return response()->json([
                'success' => true,
                'data' => $results,
                'stats' => $stats
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Obtener resultado de un estudiante específico
     */
    public function getStudentResult($evaluationId, $userId)
    {
        if (!Auth::user()->isTeacher() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para ver este resultado'
            ], 403);
        }

        $result = EvaluationResult::where('evaluation_id', $evaluationId)
            ->where('user_id', $userId)
            ->with('studentAnswers.question')
            ->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Resultado no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Duplicar una evaluación
     */
    public function duplicate($id)
    {
        $originalEvaluation = Evaluation::findOrFail($id);

        if ($originalEvaluation->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para duplicar esta evaluación'
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Duplicar evaluación
            $newEvaluation = $originalEvaluation->replicate();
            $newEvaluation->id = Str::uuid();
            $newEvaluation->title = $originalEvaluation->title . ' (Copia)';
            $newEvaluation->is_published = false;
            $newEvaluation->created_at = now();
            $newEvaluation->updated_at = now();
            $newEvaluation->save();

            // Duplicar preguntas
            foreach ($originalEvaluation->questions as $question) {
                $newQuestion = $question->replicate();
                $newQuestion->id = Str::uuid();
                $newQuestion->evaluation_id = $newEvaluation->id;
                $newQuestion->created_at = now();
                $newQuestion->updated_at = now();
                $newQuestion->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Evaluación duplicada exitosamente',
                'data' => $newEvaluation->load(['teacher', 'questions'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al duplicar la evaluación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de una evaluación (para docentes)
     */
    public function getStats($id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ($evaluation->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para ver las estadísticas de esta evaluación'
            ], 403);
        }

        $results = EvaluationResult::where('evaluation_id', $id)
            ->where('status', 'completed')
            ->get();

        $stats = [
            'total_submissions' => $results->count(),
            'average_score' => $results->avg('score') ?? 0,
            'max_score' => $results->max('score') ?? 0,
            'min_score' => $results->min('score') ?? 0,
            'median_score' => $this->calculateMedian($results->pluck('score')->toArray()),
            'passing_rate' => $this->calculatePassingRate($results),
            'score_distribution' => $this->getScoreDistribution($results)
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // ========== MÉTODOS PRIVADOS ==========

    /**
     * Verificar si una respuesta es correcta
     */
    private function checkAnswer($question, $answer)
    {
        return strtolower(trim($answer)) === strtolower(trim($question->correct_answer));
    }

    /**
     * Actualizar total de preguntas y puntos de una evaluación
     */
    private function updateEvaluationTotals($evaluationId)
    {
        $evaluation = Evaluation::find($evaluationId);
        if (!$evaluation) return;

        $totalQuestions = $evaluation->questions()->count();
        $totalPoints = $evaluation->questions()->sum('points');

        $evaluation->update([
            'total_questions' => $totalQuestions,
            'total_points' => $totalPoints
        ]);
    }

    /**
     * Actualizar perfil del estudiante
     */
    private function updateStudentProfile($userId)
    {
        $studentProfile = User::find($userId)?->studentProfile;
        if (!$studentProfile) return;

        // Calcular nuevo promedio
        $average = EvaluationResult::where('user_id', $userId)
            ->where('status', 'completed')
            ->avg('score');

        $studentProfile->average_score = round($average ?? 0, 2);
        $studentProfile->save();
    }

    /**
     * Calcular tasa de aprobación
     */
    private function calculatePassingRate($results)
    {
        $total = $results->count();
        if ($total === 0) return 0;

        $passing = $results->filter(function($result) {
            return $result->score >= 6;
        })->count();

        return round(($passing / $total) * 100, 2);
    }

    /**
     * Calcular mediana
     */
    private function calculateMedian($array)
    {
        sort($array);
        $count = count($array);
        
        if ($count === 0) return 0;
        if ($count % 2 === 0) {
            return ($array[$count/2 - 1] + $array[$count/2]) / 2;
        }
        return $array[floor($count/2)];
    }

    /**
     * Obtener distribución de puntajes
     */
    private function getScoreDistribution($results)
    {
        $distribution = [
            '0-3' => 0,
            '3-5' => 0,
            '5-7' => 0,
            '7-9' => 0,
            '9-10' => 0
        ];

        foreach ($results as $result) {
            $score = $result->score;
            if ($score < 3) $distribution['0-3']++;
            elseif ($score < 5) $distribution['3-5']++;
            elseif ($score < 7) $distribution['5-7']++;
            elseif ($score < 9) $distribution['7-9']++;
            else $distribution['9-10']++;
        }

        return $distribution;
    }
}