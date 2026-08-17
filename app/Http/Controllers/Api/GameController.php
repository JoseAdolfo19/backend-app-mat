<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GameController extends Controller
{
    private GamificationService $gamification;

    public function __construct(GamificationService $gamification)
    {
        $this->gamification = $gamification;
    }

    private function role(): ?string
    {
        return Auth::user()->role?->name;
    }

    private function isCoordinatorOrDirector(): bool
    {
        return in_array($this->role(), [Role::COORDINATOR, Role::DIRECTOR]);
    }

    private function assertCanAccessCourse(Course $course)
    {
        $user = Auth::user();
        $role = $this->role();

        if ($this->isCoordinatorOrDirector()) {
            return;
        }

        if ($role === Role::STUDENT) {
            $enrolled = Enrollment::where('course_id', $course->id)->where('student_id', $user->id)->exists();
            if (!$enrolled) {
                abort(403, 'No estás matriculado en este curso');
            }
            return;
        }

        if ($role === Role::TEACHER && (string) $course->teacher_id === (string) $user->id) {
            return;
        }

        abort(403, 'No tienes permiso para acceder a este curso');
    }

    private function canManageGame(Game $game): bool
    {
        $user = Auth::user();
        $role = $this->role();
        if ($this->isCoordinatorOrDirector()) {
            return true;
        }
        return $role === Role::TEACHER && (string) $game->teacher_id === (string) $user->id;
    }

    // ============================================================
    // LISTADO
    // ============================================================

    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $this->role();
        $query = Game::with(['course:id,name', 'teacher:id,full_name', 'submissions']);

        if ($role === Role::STUDENT) {
            $courseIds = Enrollment::where('student_id', $user->id)->pluck('course_id');
            $query->whereIn('course_id', $courseIds)->where('is_active', true);
        } elseif ($role === Role::TEACHER) {
            $query->where('teacher_id', $user->id);
        } elseif ($this->isCoordinatorOrDirector()) {
            // todos
        } else {
            abort(403, 'No tienes permiso');
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        return response()->json(['data' => $query->orderByDesc('created_at')->get()]);
    }

    public function show($gameId)
    {
        $game = Game::with(['course:id,name', 'teacher:id,full_name', 'submissions.student:id,full_name,email'])->findOrFail($gameId);

        if (!$this->canManageGame($game) && $this->role() !== Role::STUDENT) {
            abort(403, 'No tienes permiso');
        }

        if ($this->role() === Role::STUDENT) {
            $this->assertCanAccessCourse($game->course);
        }

        return response()->json(['data' => $game]);
    }

    // ============================================================
    // CRUD (docente dueño del curso / coordinador/director)
    // ============================================================

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:200',
            'url' => 'nullable|string|max:500',
            'pin' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'platform' => 'nullable|in:quizizz,kahoot,h5p,other',
            'is_active' => 'nullable|boolean',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $this->assertCanAccessCourse($course);

        $user = Auth::user();
        $role = $this->role();
        if ($role === Role::STUDENT) {
            abort(403, 'Los estudiantes no pueden crear juegos');
        }

        $teacherId = $role === Role::TEACHER ? $user->id : $course->teacher_id;

        $game = Game::create([
            'id' => Str::uuid(),
            'course_id' => $course->id,
            'teacher_id' => $teacherId,
            'title' => $validated['title'],
            'url' => $validated['url'] ?? null,
            'pin' => $validated['pin'] ?? null,
            'description' => $validated['description'] ?? null,
            'platform' => $validated['platform'] ?? 'quizizz',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['message' => 'Juego creado', 'data' => $game], 201);
    }

    public function update(Request $request, $gameId)
    {
        $game = Game::findOrFail($gameId);
        if (!$this->canManageGame($game)) {
            abort(403, 'No tienes permiso');
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'url' => 'nullable|string|max:500',
            'pin' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'platform' => 'nullable|in:quizizz,kahoot,h5p,other',
            'is_active' => 'nullable|boolean',
        ]);

        $game->update($validated);
        return response()->json(['message' => 'Juego actualizado', 'data' => $game]);
    }

    public function destroy($gameId)
    {
        $game = Game::findOrFail($gameId);
        if (!$this->canManageGame($game)) {
            abort(403, 'No tienes permiso');
        }
        $game->delete();
        return response()->json(['message' => 'Juego eliminado']);
    }

    // ============================================================
    // COMPROBANTE (estudiante matriculado)
    // ============================================================

    public function uploadScreenshot(Request $request, $gameId)
    {
        $game = Game::findOrFail($gameId);
        if ($this->role() !== Role::STUDENT) {
            abort(403, 'Solo estudiantes pueden subir comprobantes');
        }
        $this->assertCanAccessCourse($game->course);

        $request->validate([
            'file' => 'required|file|mimes:png,jpg,jpeg,webp|max:10240',
        ]);

        $file = $request->file('file');
        $name = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
        $file->storeAs('game-screenshots', $name, 'public');

        return response()->json([
            'success' => true,
            'url' => $request->root() . '/storage/game-screenshots/' . $name,
        ], 201);
    }

    public function submit(Request $request, $gameId)
    {
        $game = Game::findOrFail($gameId);
        if ($this->role() !== Role::STUDENT) {
            abort(403, 'Solo estudiantes pueden enviar comprobantes');
        }
        $this->assertCanAccessCourse($game->course);

        $validated = $request->validate([
            'score' => 'nullable|string|max:50',
            'screenshot_url' => 'nullable|string|max:500',
        ]);

        if (empty($validated['score']) && empty($validated['screenshot_url'])) {
            return response()->json(['message' => 'Debes ingresar tu puntaje o subir una captura'], 422);
        }

        $user = Auth::user();

        $submission = GameSubmission::updateOrCreate(
            ['game_id' => $game->id, 'student_id' => $user->id],
            [
                'id' => Str::uuid(),
                'score' => $validated['score'] ?? null,
                'screenshot_url' => $validated['screenshot_url'] ?? null,
                'status' => 'pending',
                'grade' => null,
                'teacher_feedback' => null,
                'xp_awarded' => 0,
                'submitted_at' => now(),
                'graded_at' => null,
            ]
        );

        return response()->json(['message' => 'Comprobante enviado, sujeto a validación del profesor', 'data' => $submission], 201);
    }

    // ============================================================
    // CALIFICACIÓN (docente dueño / coordinador/director) + XP
    // ============================================================

    public function grade(Request $request, $submissionId)
    {
        $submission = GameSubmission::with('game')->findOrFail($submissionId);
        if (!$this->canManageGame($submission->game)) {
            abort(403, 'No tienes permiso para calificar este juego');
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'grade' => 'nullable|numeric|min:0|max:20',
            'teacher_feedback' => 'nullable|string|max:500',
        ]);

        $grade = $validated['grade'] ?? null;
        $xp = 0;

        if ($validated['status'] === 'approved') {
            $xp = (int) round((float) ($grade ?? 0) * 2);
        }

        $submission->update([
            'status' => $validated['status'],
            'grade' => $grade,
            'teacher_feedback' => $validated['teacher_feedback'] ?? null,
            'xp_awarded' => $xp,
            'graded_at' => now(),
        ]);

        if ($validated['status'] === 'approved' && $xp > 0) {
            $student = $submission->student;
            $this->gamification->awardXp($student, $xp, 'quizizz_game');
        }

        return response()->json([
            'message' => $validated['status'] === 'approved' ? 'Comprobante aprobado' : 'Comprobante rechazado',
            'data' => $submission,
            'xp_awarded' => $xp,
        ]);
    }

    // ============================================================
    // CATÁLOGO de cursos para el docente
    // ============================================================

    public function teacherCourses()
    {
        $user = Auth::user();
        $role = $this->role();

        if ($role === Role::TEACHER) {
            $courses = Course::where('teacher_id', $user->id)
                ->with('salon:id,grade,section')
                ->select('id', 'name', 'salon_id', 'teacher_id')
                ->get();
        } elseif ($this->isCoordinatorOrDirector()) {
            $courses = Course::with('salon:id,grade,section')
                ->select('id', 'name', 'salon_id', 'teacher_id')
                ->get();
        } else {
            abort(403, 'No tienes permiso');
        }

        return response()->json(['data' => $courses]);
    }
}