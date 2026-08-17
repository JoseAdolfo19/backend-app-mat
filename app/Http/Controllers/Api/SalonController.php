<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SalonStudentsImport;

class SalonController extends Controller
{
    private function isStaff(): bool
    {
        $role = Auth::user()->role?->name;
        return in_array($role, [Role::COORDINATOR, Role::DIRECTOR, Role::TEACHER]);
    }

    private function isCoordinatorOrDirector(): bool
    {
        $role = Auth::user()->role?->name;
        return in_array($role, [Role::COORDINATOR, Role::DIRECTOR]);
    }

    // ============================================================
    // SALONES — CRUD (coordinador/director)
    // ============================================================

    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role?->name;

        if ($role === Role::STUDENT) {
            return $this->studentCourses($request);
        }

        if ($this->isCoordinatorOrDirector()) {
            $salones = Salon::with(['courses:id,salon_id,name', 'academicPeriod:id,name'])
                ->orderBy('grade')->orderBy('section')->get();
            return response()->json(['data' => $salones]);
        }

        // Docente: salones donde enseña algún curso
        $courseIds = Course::where('teacher_id', $user->id)->pluck('salon_id');
        $salones = Salon::with(['courses:id,salon_id,name,teacher_id', 'academicPeriod:id,name'])
            ->whereIn('id', $courseIds)
            ->orderBy('grade')->orderBy('section')->get();
        return response()->json(['data' => $salones]);
    }

    public function store(Request $request)
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'Solo coordinador o director puede crear salones'], 403);
        }

        $validated = $request->validate([
            'grade' => 'required|string|max:20',
            'section' => 'required|string|max:10',
            'academic_period_id' => 'nullable|exists:academic_periods,id',
        ]);

        $salon = Salon::create(array_merge($validated, [
            'coordinator_id' => Auth::id(),
        ]));

        return response()->json(['message' => 'Salón creado', 'data' => $salon], 201);
    }

    public function show($id)
    {
        if (!$this->isStaff()) {
            return response()->json(['message' => 'No tienes permiso'], 403);
        }

        $salon = Salon::with(['courses.teacher:id,full_name', 'courses.lessons', 'academicPeriod:id,name'])
            ->findOrFail($id);

        return response()->json(['data' => $salon]);
    }

    public function update(Request $request, $id)
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'Solo coordinador o director puede editar salones'], 403);
        }

        $validated = $request->validate([
            'grade' => 'required|string|max:20',
            'section' => 'required|string|max:10',
            'academic_period_id' => 'nullable|exists:academic_periods,id',
        ]);

        $salon = Salon::findOrFail($id);
        $salon->update($validated);

        return response()->json(['message' => 'Salón actualizado', 'data' => $salon]);
    }

    public function destroy($id)
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'Solo coordinador o director puede eliminar salones'], 403);
        }

        $salon = Salon::findOrFail($id);
        $salon->delete();

        return response()->json(['message' => 'Salón eliminado']);
    }

    // ============================================================
    // CURSOS — CRUD por salón
    // ============================================================

    public function courses($salonId)
    {
        $user = Auth::user();
        $role = $user->role?->name;

        $salon = Salon::findOrFail($salonId);

        // Docente solo puede ver cursos donde enseña
        if ($role === Role::TEACHER) {
            $courses = $salon->courses()
                ->where('teacher_id', $user->id)
                ->withCount('enrollments')
                ->get();
        } else {
            $courses = $salon->courses()
                ->with('teacher:id,full_name')
                ->withCount('enrollments')
                ->get();
        }

        return response()->json(['data' => $courses]);
    }

    public function storeCourse(Request $request, $salonId)
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'Solo coordinador o director puede crear cursos'], 403);
        }

        $salon = Salon::findOrFail($salonId);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'teacher_id' => 'required|exists:users,id',
        ]);

        $course = Course::create([
            'salon_id' => $salon->id,
            'name' => $validated['name'],
            'code' => Course::generateCode(),
            'description' => $validated['description'] ?? null,
            'teacher_id' => $validated['teacher_id'],
        ]);

        // Auto-matrícula: todos los alumnos del salón pertenecen a todos los cursos del salón
        $this->enrollSalonStudentsInCourse($salon, $course, Auth::id());

        return response()->json(['message' => 'Curso creado', 'data' => $course->load('teacher:id,full_name')], 201);
    }

    public function updateCourse(Request $request, $courseId)
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'Solo coordinador o director puede editar cursos'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'teacher_id' => 'nullable|exists:users,id',
        ]);

        $course = Course::findOrFail($courseId);
        $course->update($validated);

        return response()->json(['message' => 'Curso actualizado', 'data' => $course]);
    }

    public function destroyCourse($courseId)
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'Solo coordinador o director puede eliminar cursos'], 403);
        }

        $course = Course::findOrFail($courseId);
        $course->delete();

        return response()->json(['message' => 'Curso eliminado']);
    }

    // ============================================================
    // LECCIONES — por curso (docente del curso o coordinador/director)
    // ============================================================

    public function courseLessons($courseId)
    {
        $course = Course::with('salon:id,grade,section')->findOrFail($courseId);
        $this->assertCanAccessCourse($course);

        $lessons = $course->lessons()->orderBy('order')->get();

        return response()->json([
            'data' => $lessons,
            'course' => $course,
        ]);
    }

    public function storeLesson(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $user = Auth::user();
        $role = $user->role?->name;

        // Docente del curso o coordinador/director
        $isTeacherOfCourse = $role === Role::TEACHER && (string) $course->teacher_id === (string) $user->id;
        if (!$isTeacherOfCourse && !$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'No tienes permiso para agregar lecciones a este curso'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'content' => 'required|string',
            'topic' => 'nullable|string|max:255',
            'difficulty' => 'nullable|in:basic,intermediate,advanced',
            'estimated_time' => 'nullable|integer|min:1',
            'is_published' => 'nullable|boolean',
            'resources' => 'nullable|array',
        ]);

        $lesson = Lesson::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'],
            'teacher_id' => $course->teacher_id ?? $user->id,
            'course_id' => $course->id,
            'topic' => $validated['topic'] ?? null,
            'difficulty' => $validated['difficulty'] ?? 'basic',
            'estimated_time' => $validated['estimated_time'] ?? 45,
            'is_published' => $validated['is_published'] ?? true,
            'resources' => $validated['resources'] ?? null,
        ]);

        return response()->json(['message' => 'Lección agregada', 'data' => $lesson], 201);
    }

    // ============================================================
    // MATRÍCULA — estudiantes a cursos (coordinador/director/docente)
    // ============================================================

    public function enroll(Request $request, $courseId)
    {
        if (!$this->isStaff()) {
            return response()->json(['message' => 'No tienes permiso'], 403);
        }

        $course = Course::findOrFail($courseId);

        // Docente solo puede matricular en sus propios cursos
        $user = Auth::user();
        $role = $user->role?->name;
        if ($role === Role::TEACHER && (string) $course->teacher_id !== (string) $user->id) {
            return response()->json(['message' => 'No tienes permiso para matricular en este curso'], 403);
        }

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:users,id',
        ]);

        $studentRoleId = Role::where('name', Role::STUDENT)->first()?->id;
        $added = 0;
        foreach ($validated['student_ids'] as $studentId) {
            $student = User::find($studentId);
            if (!$student || (string) $student->role_id !== (string) $studentRoleId) {
                continue;
            }
            Enrollment::firstOrCreate([
                'course_id' => $course->id,
                'student_id' => $studentId,
            ], ['enrolled_by' => $user->id]);
            $added++;

            // Si el estudiante pertenece al salón del curso, matricularlo en todos los cursos del salón
            if ($student->salon_id && (string) $student->salon_id === (string) $course->salon_id) {
                $this->enrollStudentInSalonCourses($course->salon, $student, $user->id);
            }
        }

        return response()->json(['message' => "$added estudiante(s) matriculado(s)", 'added' => $added]);
    }

    public function unenroll(Request $request, $courseId)
    {
        if (!$this->isStaff()) {
            return response()->json(['message' => 'No tienes permiso'], 403);
        }

        $course = Course::findOrFail($courseId);

        $user = Auth::user();
        $role = $user->role?->name;
        if ($role === Role::TEACHER && (string) $course->teacher_id !== (string) $user->id) {
            return response()->json(['message' => 'No tienes permiso'], 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
        ]);

        Enrollment::where('course_id', $course->id)
            ->where('student_id', $validated['student_id'])
            ->delete();

        return response()->json(['message' => 'Estudiante retirado del curso']);
    }

    public function courseStudents($courseId)
    {
        $course = Course::findOrFail($courseId);
        $this->assertCanAccessCourse($course);

        $students = $course->students()->select('users.id', 'users.full_name', 'users.email')->get();

        return response()->json(['data' => $students]);
    }

    // ============================================================
    // ALUMNOS DEL SALÓN — registro y listado
    // ============================================================

    public function salonStudents(Request $request, $salonId)
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'No tienes permiso'], 403);
        }

        $salon = Salon::findOrFail($salonId);

        $query = $salon->students()->select('id', 'full_name', 'email', 'dni', 'salon_id', 'grade');

        if ($request->has('search') && trim($request->search) !== '') {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('dni', 'like', "%{$term}%");
            });
        }

        $students = $query->orderBy('full_name')->get();

        return response()->json(['data' => $students]);
    }

    public function storeStudent(Request $request, $salonId)
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'Solo coordinador o director puede registrar alumnos'], 403);
        }

        $salon = Salon::findOrFail($salonId);

        $validated = $request->validate([
            'dni' => 'required|string|max:8|unique:users,dni',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'grade' => 'nullable|string|max:20',
        ]);

        $studentRoleId = Role::where('name', Role::STUDENT)->first()?->id;

        $student = User::create([
            'dni' => $validated['dni'],
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $studentRoleId,
            'salon_id' => $salon->id,
            'grade' => $validated['grade'] ?? $salon->grade,
            'is_active' => true,
            'institution' => $salon->display_name,
            'provider' => 'email',
        ]);

        // Auto-matrícula: el alumno pertenece a todos los cursos de su salón
        $this->enrollStudentInSalonCourses($salon, $student, Auth::id());

        return response()->json([
            'message' => 'Alumno registrado y matriculado en los cursos del salón',
            'data' => $student->only('id', 'dni', 'full_name', 'email', 'salon_id', 'grade'),
        ], 201);
    }

    public function importStudents(Request $request, $salonId)
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'No tienes permiso'], 403);
        }

        $salon = Salon::findOrFail($salonId);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $import = new SalonStudentsImport(
            $salon,
            Auth::id(),
            (string) config('app.default_student_password', 'password123')
        );

        Excel::import($import, $request->file('file'));

        return response()->json([
            'message' => "$import->imported alumno(s) importado(s)",
            'imported' => $import->imported,
            'errors' => $import->errors,
        ]);
    }

    public function enrollByCode(Request $request)
    {
        $user = Auth::user();
        if ($user->role?->name !== Role::STUDENT) {
            return response()->json(['message' => 'Solo estudiantes pueden auto-matricularse'], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:20',
        ]);

        $course = Course::where('code', strtoupper(trim($validated['code'])))->first();

        if (!$course) {
            return response()->json(['message' => 'Código de curso no válido'], 422);
        }

        // El estudiante debe pertenecer al salón del curso para matricularse
        if (!$user->salon_id || (string) $user->salon_id !== (string) $course->salon_id) {
            return response()->json(['message' => 'Solo puedes matricularte en cursos de tu salón'], 403);
        }

        $created = Enrollment::firstOrCreate(
            ['course_id' => $course->id, 'student_id' => $user->id],
            ['enrolled_by' => $user->id]
        )->wasRecentlyCreated;

        return response()->json([
            'message' => $created ? 'Te matriculaste correctamente en el curso' : 'Ya estás matriculado en este curso',
            'data' => $course->load(['salon:id,grade,section', 'teacher:id,full_name']),
        ]);
    }

    // ============================================================
    // HELPERS — auto-matrícula por pertenencia al salón
    // ============================================================

    private function enrollStudentInSalonCourses(Salon $salon, User $student, ?string $byUserId = null): void
    {
        $courseIds = $salon->courses()->pluck('id');
        foreach ($courseIds as $courseId) {
            Enrollment::firstOrCreate(
                ['course_id' => $courseId, 'student_id' => $student->id],
                ['enrolled_by' => $byUserId]
            );
        }
    }

    private function enrollSalonStudentsInCourse(Salon $salon, Course $course, ?string $byUserId = null): void
    {
        $studentIds = $salon->students()->pluck('id');
        foreach ($studentIds as $studentId) {
            Enrollment::firstOrCreate(
                ['course_id' => $course->id, 'student_id' => $studentId],
                ['enrolled_by' => $byUserId]
            );
        }
    }

    // ============================================================
    // ESTUDIANTE — sus cursos matriculados
    // ============================================================

    public function studentCourses(Request $request)
    {
        $user = Auth::user();

        $courses = Course::whereIn('id', Enrollment::where('student_id', $user->id)->pluck('course_id'))
            ->with(['salon:id,grade,section', 'teacher:id,full_name'])
            ->get();

        return response()->json(['data' => $courses]);
    }

    // ============================================================
    // CATÁLOGOS (coordinador/director): docentes y estudiantes
    // ============================================================

    public function teachers()
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'No tienes permiso'], 403);
        }

        $teacherRoleId = Role::where('name', Role::TEACHER)->first()?->id;
        $users = User::where('role_id', $teacherRoleId)
            ->where('is_active', true)
            ->select('id', 'full_name', 'email')
            ->orderBy('full_name')
            ->get();

        return response()->json(['data' => $users]);
    }

    public function students()
    {
        if (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'No tienes permiso'], 403);
        }

        $studentRoleId = Role::where('name', Role::STUDENT)->first()?->id;
        $users = User::where('role_id', $studentRoleId)
            ->where('is_active', true)
            ->select('id', 'full_name', 'email')
            ->orderBy('full_name')
            ->get();

        return response()->json(['data' => $users]);
    }

    public function studentCourseDetail($courseId)
    {
        $user = Auth::user();
        $role = $user->role?->name;

        $course = Course::with(['salon:id,grade,section', 'teacher:id,full_name'])
            ->findOrFail($courseId);

        if ($role === Role::STUDENT) {
            $enrolled = Enrollment::where('course_id', $courseId)->where('student_id', $user->id)->exists();
            if (!$enrolled) {
                return response()->json(['message' => 'No estás matriculado en este curso'], 403);
            }
        } elseif ($role === Role::TEACHER) {
            if ($course->teacher_id !== $user->id) {
                return response()->json(['message' => 'No tienes permiso'], 403);
            }
        } elseif (!$this->isCoordinatorOrDirector()) {
            return response()->json(['message' => 'No tienes permiso'], 403);
        }

        $lessons = $course->lessons()->where('is_published', true)->orderBy('order')->get();

        return response()->json([
            'data' => $course,
            'lessons' => $lessons,
        ]);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function assertCanAccessCourse(Course $course)
    {
        $user = Auth::user();
        $role = $user->role?->name;

        if (in_array($role, [Role::COORDINATOR, Role::DIRECTOR])) {
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
}