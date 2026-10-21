<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EvaluationResult;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Panel institucional para directores y coordinadores: métricas globales,
 * seguimiento de docentes y resumen de salones.
 */
class InstitutionController extends Controller
{
    /** Devuelve las estadísticas globales de la institución. */
    public function dashboard()
    {
        $teacherRoleId = Role::where('name', Role::TEACHER)->first()?->id;
        $studentRoleId = Role::where('name', Role::STUDENT)->first()?->id;

        $totalSalones = Salon::count();
        $totalCourses = Course::count();
        $totalTeachers = $teacherRoleId ? User::where('role_id', $teacherRoleId)->where('is_active', true)->count() : 0;
        $totalStudents = $studentRoleId ? User::where('role_id', $studentRoleId)->where('is_active', true)->count() : 0;

        $averageScore = (float) EvaluationResult::where('status', 'completed')->avg('score');

        // Top de salones por promedio de evaluaciones de sus estudiantes matriculados.
        $topSalones = Salon::query()
            ->select('salones.id', 'salones.grade', 'salones.section')
            ->join('courses', 'courses.salon_id', '=', 'salones.id')
            ->join('enrollments', 'enrollments.course_id', '=', 'courses.id')
            ->join('evaluation_results', 'evaluation_results.user_id', '=', 'enrollments.student_id')
            ->where('evaluation_results.status', 'completed')
            ->groupBy('salones.id', 'salones.grade', 'salones.section')
            ->selectRaw('AVG(evaluation_results.score) as average_score, COUNT(DISTINCT evaluation_results.id) as total_results')
            ->orderByDesc('average_score')
            ->limit(5)
            ->get();

        $stats = [
            'total_salones' => $totalSalones,
            'total_courses' => $totalCourses,
            'total_teachers' => $totalTeachers,
            'total_students' => $totalStudents,
            'total_enrollments' => Enrollment::count(),
            'total_lessons' => Lesson::count(),
            'published_lessons' => Lesson::where('is_published', true)->count(),
            'total_exams' => Exam::count(),
            'active_exams' => Exam::where('is_active', true)->count(),
            'average_score' => round($averageScore, 1),
            'top_salones' => $topSalones,
        ];

        return response()->json(['data' => $stats]);
    }

    /** Lista el personal docente con métricas de su actividad académica. */
    public function teachers()
    {
        $teacherRoleId = Role::where('name', Role::TEACHER)->first()?->id;

        $teachers = User::where('role_id', $teacherRoleId)
            ->where('is_active', true)
            ->select('id', 'full_name', 'email', 'last_login')
            ->orderBy('full_name')
            ->get();

        $courseCounts = Course::selectRaw('teacher_id, COUNT(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        $lessonCounts = Lesson::selectRaw('teacher_id, COUNT(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        $evaluationCounts = \App\Models\Evaluation::selectRaw('teacher_id, COUNT(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        $examCounts = Exam::selectRaw('teacher_id, COUNT(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        $data = $teachers->map(function ($teacher) use ($courseCounts, $lessonCounts, $evaluationCounts, $examCounts) {
            return [
                'id' => $teacher->id,
                'full_name' => $teacher->full_name,
                'email' => $teacher->email,
                'last_login' => $teacher->last_login,
                'total_courses' => (int) ($courseCounts[$teacher->id] ?? 0),
                'total_lessons' => (int) ($lessonCounts[$teacher->id] ?? 0),
                'total_evaluations' => (int) ($evaluationCounts[$teacher->id] ?? 0),
                'total_exams' => (int) ($examCounts[$teacher->id] ?? 0),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /** Resumen de salones con sus cursos, estudiantes y docentes asignados. */
    public function overview()
    {
        $salones = Salon::with(['courses.teacher:id,full_name'])
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        $data = $salones->map(function ($salon) {
            return [
                'id' => $salon->id,
                'grade' => $salon->grade,
                'section' => $salon->section,
                'display_name' => $salon->display_name ?? ($salon->grade . ' - ' . $salon->section),
                'total_students' => $salon->students_count,
                'courses' => $salon->courses->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'teacher' => $c->teacher?->full_name,
                ]),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
