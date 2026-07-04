<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    /**
     * Listar todas las lecciones con filtros
     */
    public function index(Request $request)
    {
        $query = Lesson::query()->with(['teacher', 'progress']);

        // Filtros
        if ($request->has('difficulty') && $request->difficulty) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->has('unit') && $request->unit) {
            $query->where('unit', 'LIKE', '%' . $request->unit . '%');
        }

        if ($request->has('topic') && $request->topic) {
            $query->where('topic', 'LIKE', '%' . $request->topic . '%');
        }

        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('description', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('content', 'LIKE', '%' . $request->search . '%');
            });
        }

        if ($request->has('tag') && $request->tag) {
            $query->whereJsonContains('tags', $request->tag);
        }

        // Si es estudiante, solo mostrar lecciones publicadas
        if (Auth::user()->isStudent()) {
            $query->where('is_published', true);
        }

        // Si es docente, mostrar sus lecciones
        if (Auth::user()->isTeacher()) {
            $query->where('teacher_id', Auth::id());
        }

        $lessons = $query->orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        // Agregar información de progreso para estudiantes
        if (Auth::user()->isStudent()) {
            foreach ($lessons as $lesson) {
                $progress = LessonProgress::where('user_id', Auth::id())
                    ->where('lesson_id', $lesson->id)
                    ->first();
                $lesson->user_progress = $progress;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $lessons
        ]);
    }

    /**
     * Mostrar una lección específica con su contenido completo
     */
    public function show($id)
    {
        $lesson = Lesson::with(['teacher', 'evaluations'])
            ->findOrFail($id);

        // Verificar si el estudiante puede ver esta lección
        if (Auth::user()->isStudent() && !$lesson->is_published) {
            return response()->json([
                'success' => false,
                'message' => 'Esta lección no está disponible'
            ], 403);
        }

        // Obtener progreso del estudiante
        if (Auth::user()->isStudent()) {
            $progress = LessonProgress::where('user_id', Auth::id())
                ->where('lesson_id', $id)
                ->first();

            if (!$progress) {
                $progress = LessonProgress::create([
                    'id' => Str::uuid(),
                    'user_id' => Auth::id(),
                    'lesson_id' => $id,
                    'progress' => 0,
                    'status' => LessonProgress::STATUS_NOT_STARTED,
                    'time_spent' => 0,
                    'last_position' => 0
                ]);
            }

            $lesson->user_progress = $progress;
        }

        // Incrementar contador de vistas
        $lesson->increment('views_count');

        return response()->json([
            'success' => true,
            'data' => $lesson
        ]);
    }

    /**
     * Crear una nueva lección
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'unit' => 'nullable|string|max:255',
            'topic' => 'nullable|string|max:255',
            'difficulty' => 'required|in:basic,intermediate,advanced',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'estimated_time' => 'nullable|integer|min:1|max:999',
            'resources' => 'nullable|array',
            'resources.*.type' => 'required|in:pdf,video,image,link,audio',
            'resources.*.url' => 'required|string|url',
            'resources.*.title' => 'required|string|max:255',
            'order' => 'nullable|integer|min:0'
        ]);

        $lesson = Lesson::create([
            'id' => Str::uuid(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'],
            'teacher_id' => Auth::id(),
            'unit' => $validated['unit'] ?? null,
            'topic' => $validated['topic'] ?? null,
            'difficulty' => $validated['difficulty'],
            'tags' => $validated['tags'] ?? [],
            'estimated_time' => $validated['estimated_time'] ?? 45,
            'resources' => $validated['resources'] ?? [],
            'order' => $validated['order'] ?? 0,
            'is_published' => false,
            'views_count' => 0
        ]);

        // Crear notificación para estudiantes (opcional)
        // NotificationController::createBulkNotifications(...);

        return response()->json([
            'success' => true,
            'message' => 'Lección creada exitosamente',
            'data' => $lesson->load('teacher')
        ], 201);
    }

    /**
     * Actualizar una lección existente
     */
    public function update(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);

        // Verificar que el usuario sea el propietario o admin
        if ($lesson->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para editar esta lección'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'content' => 'sometimes|string',
            'unit' => 'nullable|string|max:255',
            'topic' => 'nullable|string|max:255',
            'difficulty' => 'sometimes|in:basic,intermediate,advanced',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'estimated_time' => 'nullable|integer|min:1|max:999',
            'resources' => 'nullable|array',
            'resources.*.type' => 'required|in:pdf,video,image,link,audio',
            'resources.*.url' => 'required|string|url',
            'resources.*.title' => 'required|string|max:255',
            'order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean'
        ]);

        $lesson->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lección actualizada exitosamente',
            'data' => $lesson->load('teacher')
        ]);
    }

    /**
     * Eliminar una lección (soft delete)
     */
    public function destroy($id)
    {
        $lesson = Lesson::findOrFail($id);

        // Verificar que el usuario sea el propietario o admin
        if ($lesson->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar esta lección'
            ], 403);
        }

        // Verificar si tiene evaluaciones asociadas
        if ($lesson->evaluations()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la lección porque tiene evaluaciones asociadas'
            ], 400);
        }

        $lesson->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lección eliminada exitosamente'
        ]);
    }

    /**
     * Publicar una lección
     */
    public function publish($id)
    {
        $lesson = Lesson::findOrFail($id);

        // Verificar que el usuario sea el propietario o admin
        if ($lesson->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para publicar esta lección'
            ], 403);
        }

        // Verificar que tenga contenido
        if (empty($lesson->content)) {
            return response()->json([
                'success' => false,
                'message' => 'La lección debe tener contenido para ser publicada'
            ], 400);
        }

        $lesson->update([
            'is_published' => true,
            'published_at' => now()
        ]);

        // Crear notificación para estudiantes
        // NotificationController::createBulkNotifications(...);

        return response()->json([
            'success' => true,
            'message' => 'Lección publicada exitosamente',
            'data' => $lesson
        ]);
    }

    /**
     * Despublicar una lección
     */
    public function unpublish($id)
    {
        $lesson = Lesson::findOrFail($id);

        if ($lesson->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para despublicar esta lección'
            ], 403);
        }

        $lesson->update([
            'is_published' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lección despublicada exitosamente'
        ]);
    }

    /**
     * Obtener recursos de una lección
     */
    public function getResources($id)
    {
        $lesson = Lesson::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $lesson->resources ?? []
        ]);
    }

    /**
     * Agregar recurso a una lección
     */
    public function addResource(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);

        if ($lesson->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para agregar recursos'
            ], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:pdf,video,image,link,audio',
            'url' => 'required|string|url',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $resources = $lesson->resources ?? [];
        $resources[] = [
            'id' => Str::uuid(),
            'type' => $validated['type'],
            'url' => $validated['url'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'created_at' => now()
        ];

        $lesson->update(['resources' => $resources]);

        return response()->json([
            'success' => true,
            'message' => 'Recurso agregado exitosamente',
            'data' => $resources
        ]);
    }

    /**
     * Eliminar recurso de una lección
     */
    public function removeResource($id, $resourceId)
    {
        $lesson = Lesson::findOrFail($id);

        if ($lesson->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar recursos'
            ], 403);
        }

        $resources = array_filter($lesson->resources ?? [], function($resource) use ($resourceId) {
            return $resource['id'] !== $resourceId;
        });

        $lesson->update(['resources' => array_values($resources)]);

        return response()->json([
            'success' => true,
            'message' => 'Recurso eliminado exitosamente',
            'data' => array_values($resources)
        ]);
    }

    /**
     * Duplicar una lección
     */
    public function duplicate($id)
    {
        $originalLesson = Lesson::findOrFail($id);

        if ($originalLesson->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para duplicar esta lección'
            ], 403);
        }

        $newLesson = $originalLesson->replicate();
        $newLesson->id = Str::uuid();
        $newLesson->title = $originalLesson->title . ' (Copia)';
        $newLesson->is_published = false;
        $newLesson->views_count = 0;
        $newLesson->created_at = now();
        $newLesson->updated_at = now();
        $newLesson->save();

        return response()->json([
            'success' => true,
            'message' => 'Lección duplicada exitosamente',
            'data' => $newLesson->load('teacher')
        ]);
    }

    /**
     * Obtener lecciones por unidad
     */
    public function getByUnit($unit)
    {
        $lessons = Lesson::where('unit', $unit)
            ->where('is_published', true)
            ->orderBy('order', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lessons
        ]);
    }

    /**
     * Obtener estadísticas de una lección (para docentes)
     */
    public function getStats($id)
    {
        $lesson = Lesson::findOrFail($id);

        if ($lesson->teacher_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para ver las estadísticas de esta lección'
            ], 403);
        }

        $stats = [
            'total_views' => $lesson->views_count ?? 0,
            'students_started' => LessonProgress::where('lesson_id', $id)->count(),
            'students_completed' => LessonProgress::where('lesson_id', $id)
                ->where('status', LessonProgress::STATUS_COMPLETED)
                ->count(),
            'average_progress' => LessonProgress::where('lesson_id', $id)->avg('progress') ?? 0,
            'average_time_spent' => LessonProgress::where('lesson_id', $id)->avg('time_spent') ?? 0,
            'completion_rate' => $this->calculateCompletionRate($id)
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Calcular tasa de finalización
     */
    private function calculateCompletionRate($lessonId)
    {
        $total = LessonProgress::where('lesson_id', $lessonId)->count();
        if ($total === 0) return 0;

        $completed = LessonProgress::where('lesson_id', $lessonId)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->count();

        return round(($completed / $total) * 100, 2);
    }
}