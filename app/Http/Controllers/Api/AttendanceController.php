<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Registra y consulta la asistencia de estudiantes por curso y fecha.
 */
class AttendanceController extends Controller
{
    private function isCoordinatorOrDirector(): bool
    {
        $role = Auth::user()->role?->name;
        return in_array($role, [Role::COORDINATOR, Role::DIRECTOR]);
    }

    /** Comprueba que el docente actual pueda administrar la asistencia del curso. */
    private function assertCanManageCourse(Course $course): void
    {
        $user = Auth::user();

        if ($this->isCoordinatorOrDirector()) {
            return;
        }

        if ($user->isTeacher() && (string) $course->teacher_id === (string) $user->id) {
            return;
        }

        abort(403, __('attendance_course_forbidden'));
    }

    /** Guarda o actualiza la asistencia de varios estudiantes de un curso en una fecha. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'date' => 'required|date|before_or_equal:today',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|exists:users,id',
            'records.*.status' => 'required|in:present,absent,late,excused',
            'records.*.note' => 'nullable|string|max:255',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $this->assertCanManageCourse($course);

        $enrolledIds = Enrollment::where('course_id', $course->id)->pluck('student_id');

        $saved = 0;
        foreach ($validated['records'] as $record) {
            if (!$enrolledIds->contains($record['student_id'])) {
                continue;
            }

            Attendance::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'student_id' => $record['student_id'],
                    'date' => $validated['date'],
                ],
                [
                    'id' => Str::uuid(),
                    'status' => $record['status'],
                    'note' => $record['note'] ?? null,
                    'recorded_by' => Auth::id(),
                ]
            );
            $saved++;
        }

        return response()->json([
            'message' => __('attendance_saved'),
            'saved' => $saved,
        ]);
    }

    /** Devuelve la asistencia de un curso en una fecha, combinando matriculados y registros. */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'date' => 'required|date',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $this->assertCanManageCourse($course);

        $students = $course->students()
            ->select('users.id', 'users.full_name')
            ->orderBy('users.full_name')
            ->get();

        $records = Attendance::where('course_id', $course->id)
            ->where('date', $validated['date'])
            ->get()
            ->keyBy('student_id');

        $data = $students->map(function ($student) use ($records) {
            $record = $records->get($student->id);
            return [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'status' => $record->status ?? null,
                'note' => $record->note ?? null,
                'recorded' => $record !== null,
            ];
        });

        return response()->json([
            'course' => $course->only('id', 'name'),
            'date' => $validated['date'],
            'data' => $data,
        ]);
    }

    /** Historial de asistencia del estudiante autenticado. */
    public function my(Request $request)
    {
        $user = Auth::user();

        $query = Attendance::where('student_id', $user->id)
            ->with('course:id,name');

        if ($request->filled('course_id')) {
            $request->validate(['course_id' => 'exists:courses,id']);
            $query->where('course_id', $request->course_id);
        }

        $attendance = $query->orderByDesc('date')->paginate(min((int) ($request->per_page ?? 20), 100));

        return response()->json([
            'data' => $attendance->items(),
            'meta' => [
                'current_page' => $attendance->currentPage(),
                'total' => $attendance->total(),
            ],
        ]);
    }

    /** Resumen de asistencia por estudiante para un curso. */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $this->assertCanManageCourse($course);

        $students = $course->students()
            ->select('users.id', 'users.full_name')
            ->orderBy('users.full_name')
            ->get();

        $totals = Attendance::where('course_id', $course->id)
            ->selectRaw('student_id, status, COUNT(*) as total')
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id');

        $data = $students->map(function ($student) use ($totals) {
            $rows = $totals->get($student->id, collect());
            $counts = [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'excused' => 0,
            ];
            $total = 0;
            foreach ($rows as $row) {
                $counts[$row->status] = (int) $row->total;
                $total += (int) $row->total;
            }

            $attended = $counts['present'] + $counts['late'] + $counts['excused'];

            return [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'counts' => $counts,
                'total_records' => $total,
                'attendance_rate' => $total > 0 ? round(($attended / $total) * 100, 1) : null,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
