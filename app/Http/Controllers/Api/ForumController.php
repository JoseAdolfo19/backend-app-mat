<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForumThread;
use App\Models\ForumPost;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumController extends Controller
{
    /**
     * Hilos visibles para el usuario autenticado.
     * - Docente/coordinador/director: sus propios hilos.
     * - Estudiante: hilos de docentes con los que tiene relación académica.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role?->name;

        $query = ForumThread::with(['teacher:id,full_name', 'lesson:id,title'])
            ->withCount('posts');

        if (in_array($role, ['teacher', 'coordinador', 'director'])) {
            $query->where('teacher_id', $user->id);
        } else {
            $teacherIds = Evaluation::whereIn('id', EvaluationResult::where('user_id', $user->id)->pluck('evaluation_id'))
                ->pluck('teacher_id');
            $query->whereIn('teacher_id', $teacherIds);
        }

        $threads = $query->orderByDesc('created_at')->get();

        return response()->json(['data' => $threads]);
    }

    /**
     * Detalle de un hilo con sus posts (solo accesible por docentes del hilo
     * o estudiantes con relación con el docente autor).
     */
    public function show(Request $request, $threadId)
    {
        $user = Auth::user();
        $thread = ForumThread::with(['teacher:id,full_name', 'lesson:id,title'])->findOrFail($threadId);

        $this->assertCanView($user, $thread);

        $posts = $thread->posts()->with('user:id,full_name,role_id')->orderBy('created_at')->get();

        return response()->json([
            'data' => $thread,
            'posts' => $posts,
        ]);
    }

    /**
     * Crear hilo (docente/coordinador/director).
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role?->name, ['teacher', 'coordinador', 'director', 'admin'])) {
            return response()->json(['message' => 'Solo docentes pueden crear hilos'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:10000',
            'lesson_id' => 'nullable|exists:lessons,id',
        ]);

        $thread = ForumThread::create([
            'teacher_id' => $user->id,
            'lesson_id' => $validated['lesson_id'] ?? null,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return response()->json([
            'message' => 'Hilo creado',
            'data' => $thread->load(['teacher:id,full_name', 'lesson:id,title']),
        ], 201);
    }

    /**
     * Comentar en un hilo (docente autor o estudiante con relación).
     */
    public function post(Request $request, $threadId)
    {
        $user = Auth::user();
        $thread = ForumThread::findOrFail($threadId);

        $this->assertCanView($user, $thread);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $post = ForumPost::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        return response()->json([
            'message' => 'Comentario publicado',
            'data' => $post->load('user:id,full_name,role_id'),
        ], 201);
    }

    /**
     * Cerrar un hilo (solo el docente autor).
     */
    public function close(Request $request, $threadId)
    {
        $user = Auth::user();
        $thread = ForumThread::findOrFail($threadId);

        if ((string) $thread->teacher_id !== (string) $user->id) {
            return response()->json(['message' => 'Solo el docente autor puede cerrar el hilo'], 403);
        }

        $thread->update(['status' => ForumThread::STATUS_CLOSED]);

        return response()->json(['message' => 'Hilo cerrado', 'data' => $thread]);
    }

    private function assertCanView($user, $thread)
    {
        $role = $user->role?->name;
        if (in_array($role, ['teacher', 'coordinador', 'director'])) {
            if ((string) $thread->teacher_id !== (string) $user->id) {
                abort(403, 'No tienes permiso para ver este hilo');
            }
            return;
        }

        $teacherIds = Evaluation::whereIn('id', EvaluationResult::where('user_id', $user->id)->pluck('evaluation_id'))
            ->pluck('teacher_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (!in_array((string) $thread->teacher_id, $teacherIds)) {
            abort(403, 'No tienes permiso para ver este hilo');
        }
    }
}