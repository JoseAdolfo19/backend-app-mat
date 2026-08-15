<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubmittedWork;
use App\Models\LessonProgress;
use App\Models\EvaluationResult;
use App\Models\ExamAttempt;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubmittedWorkController extends Controller
{
    /**
     * List submitted works with filters.
     */
    public function index(Request $request)
    {
        $query = SubmittedWork::with(['student', 'lesson', 'evaluation', 'exam']);

        $user = Auth::user();

        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isTeacher()) {
            $teacherEvaluationIds = Evaluation::where('teacher_id', $user->id)->pluck('id');
            $teacherLessonIds = \App\Models\Lesson::where('teacher_id', $user->id)->pluck('id');
            $teacherExamIds = \App\Models\Exam::where('teacher_id', $user->id)->pluck('id');

            $query->where(function ($q) use ($teacherEvaluationIds, $teacherLessonIds, $teacherExamIds) {
                $q->whereIn('evaluation_id', $teacherEvaluationIds)
                  ->orWhereIn('lesson_id', $teacherLessonIds)
                  ->orWhereIn('exam_id', $teacherExamIds);
            });
        } elseif ($user->isParent()) {
            $childIds = $user->children()->pluck('users.id');
            $query->whereIn('student_id', $childIds);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }
        if ($request->filled('evaluation_id')) {
            $query->where('evaluation_id', $request->evaluation_id);
        }
        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('work_type')) {
            $query->where('work_type', $request->work_type);
        }

        $works = $query->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->per_page ?? 20), 50));

        return response()->json([
            'data' => $works->items(),
            'meta' => [
                'current_page' => $works->currentPage(),
                'total' => $works->total(),
            ],
        ]);
    }

    /**
     * Store a new submitted work.
     */
    public function store(Request $request)
    {
        $request->validate([
            'lesson_id' => 'nullable|exists:lessons,id',
            'evaluation_id' => 'nullable|exists:evaluations,id',
            'exam_id' => 'nullable|exists:exams,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        $user = Auth::user();

        if (!$request->lesson_id && !$request->evaluation_id && !$request->exam_id) {
            return response()->json(['message' => 'At least one of lesson_id, evaluation_id, or exam_id is required.'], 422);
        }

        $workType = 'lesson';
        if ($request->evaluation_id) {
            $workType = 'evaluation';
        } elseif ($request->exam_id) {
            $workType = 'exam';
        }

        $score = null;
        $status = 'pending';

        if ($request->evaluation_id) {
            $result = EvaluationResult::where('user_id', $user->id)
                ->where('evaluation_id', $request->evaluation_id)
                ->first();
            if ($result) {
                $score = (int) $result->score;
                $status = 'submitted';
            }
        } elseif ($request->lesson_id) {
            $progress = LessonProgress::where('user_id', $user->id)
                ->where('lesson_id', $request->lesson_id)
                ->first();
            if ($progress && $progress->status === 'completed') {
                $status = 'submitted';
            }
        } elseif ($request->exam_id) {
            $attempt = ExamAttempt::where('student_id', $user->id)
                ->where('exam_id', $request->exam_id)
                ->where('status', 'completed')
                ->first();
            if ($attempt) {
                $score = $attempt->score;
                $status = 'submitted';
            }
        }

        $work = SubmittedWork::create([
            'student_id' => $user->id,
            'lesson_id' => $request->lesson_id,
            'evaluation_id' => $request->evaluation_id,
            'exam_id' => $request->exam_id,
            'work_type' => $workType,
            'title' => $request->title,
            'description' => $request->description,
            'status' => $status,
            'score' => $score,
            'max_score' => 20,
            'attachments' => $request->attachments,
            'submitted_at' => $status === 'submitted' ? now() : null,
        ]);

        return response()->json(['data' => $work->load(['student', 'lesson', 'evaluation', 'exam'])], 201);
    }

    /**
     * Show a single submitted work.
     */
    public function show($id)
    {
        $work = SubmittedWork::with(['student', 'lesson', 'evaluation', 'exam'])->findOrFail($id);

        $user = Auth::user();
        if ($user->isStudent() && $work->student_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ($user->isParent() && !$user->children()->whereKey($work->student_id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $work]);
    }

    /**
     * Teacher grades a submitted work.
     */
    public function grade(Request $request, $id)
    {
        $request->validate([
            'score' => 'required|integer|min:0|max:20',
            'feedback' => 'nullable|string',
        ]);

        $work = SubmittedWork::findOrFail($id);

        $work->update([
            'score' => $request->score,
            'teacher_feedback' => $request->feedback ?? $work->teacher_feedback,
            'status' => 'graded',
            'graded_at' => now(),
        ]);

        return response()->json(['data' => $work->fresh()->load(['student', 'lesson', 'evaluation', 'exam'])]);
    }

    /**
     * Teacher returns work with feedback.
     */
    public function returnWork(Request $request, $id)
    {
        $request->validate([
            'feedback' => 'required|string',
        ]);

        $work = SubmittedWork::findOrFail($id);

        $work->update([
            'teacher_feedback' => $request->feedback,
            'status' => 'returned',
        ]);

        return response()->json(['data' => $work->fresh()->load(['student', 'lesson', 'evaluation', 'exam'])]);
    }

    /**
     * Student's work summary.
     */
    public function studentSummary(Request $request)
    {
        $user = Auth::user();
        if ($user->isParent()) {
            $childIds = $user->children()->pluck('users.id');
            $studentId = $request->student_id ?? null;
            if (!$studentId || !in_array($studentId, $childIds->all(), true)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($user->isTeacher()) {
            $studentId = $request->student_id ?? $user->id;
        } else {
            $studentId = $user->isStudent() ? $user->id : ($request->student_id ?? $user->id);
        }

        $totalWorks = SubmittedWork::where('student_id', $studentId)->count();
        $submitted = SubmittedWork::where('student_id', $studentId)->where('status', 'submitted')->count();
        $graded = SubmittedWork::where('student_id', $studentId)->where('status', 'graded')->count();
        $pending = SubmittedWork::where('student_id', $studentId)->where('status', 'pending')->count();
        $averageGrade = SubmittedWork::where('student_id', $studentId)->where('status', 'graded')->avg('score');

        $submittedPct = $totalWorks > 0 ? round(($submitted / $totalWorks) * 100, 2) : 0;

        $areas = ['Álgebra', 'Geometría', 'Trigonometría'];
        $byArea = [];

        foreach ($areas as $area) {
            $works = SubmittedWork::where('student_id', $studentId)
                ->where('status', 'graded')
                ->whereHas('lesson', fn ($q) => $q->where('unit', $area))
                ->orWhere(function ($q) use ($studentId, $area) {
                    $q->where('student_id', $studentId)
                      ->where('status', 'graded')
                      ->whereHas('evaluation', fn ($eq) => $eq->where('lesson_id', function ($lq) use ($area) {
                          $lq->select('id')->from('lessons')->where('unit', $area);
                      }));
                })
                ->get();

            $byArea[] = [
                'area' => $area,
                'total_works' => $works->count(),
                'average_score' => $works->count() > 0 ? round($works->avg('score'), 2) : null,
            ];
        }

        return response()->json([
            'data' => [
                'student_id' => $studentId,
                'total_works' => $totalWorks,
                'submitted' => $submitted,
                'graded' => $graded,
                'pending' => $pending,
                'percentage_submitted' => $submittedPct,
                'average_grade' => $averageGrade ? round($averageGrade, 2) : null,
                'by_area' => $byArea,
            ],
        ]);
    }

    /**
     * Auto-generate submitted_works from completed lesson_progress and evaluation_results.
     */
    public function autoGenerateFromCompleted(Request $request)
    {
        $user = Auth::user();

        $teacherLessonIds = \App\Models\Lesson::where('teacher_id', $user->id)->pluck('id')->toArray();
        $teacherEvaluationIds = Evaluation::where('teacher_id', $user->id)->pluck('id')->toArray();
        $teacherExamIds = \App\Models\Exam::where('teacher_id', $user->id)->pluck('id')->toArray();

        $created = 0;

        // From completed lessons
        $completedLessons = LessonProgress::where('status', 'completed')
            ->whereIn('lesson_id', $teacherLessonIds)
            ->get();

        foreach ($completedLessons as $lp) {
            $exists = SubmittedWork::where('student_id', $lp->user_id)
                ->where('lesson_id', $lp->lesson_id)
                ->where('work_type', 'lesson')
                ->exists();

            if (!$exists) {
                $lesson = \App\Models\Lesson::find($lp->lesson_id);
                SubmittedWork::create([
                    'student_id' => $lp->user_id,
                    'lesson_id' => $lp->lesson_id,
                    'work_type' => 'lesson',
                    'title' => $lesson?->title ?? 'Lesson Work',
                    'status' => 'submitted',
                    'score' => null,
                    'max_score' => 20,
                    'submitted_at' => $lp->completed_at,
                ]);
                $created++;
            }
        }

        // From evaluation results
        $evalResults = EvaluationResult::where('status', 'completed')
            ->whereIn('evaluation_id', $teacherEvaluationIds)
            ->get();

        foreach ($evalResults as $er) {
            $exists = SubmittedWork::where('student_id', $er->user_id)
                ->where('evaluation_id', $er->evaluation_id)
                ->where('work_type', 'evaluation')
                ->exists();

            if (!$exists) {
                $evaluation = Evaluation::find($er->evaluation_id);
                $scaledScore = $er->max_score > 0 ? (int) round(($er->score / $er->max_score) * 20) : 0;
                SubmittedWork::create([
                    'student_id' => $er->user_id,
                    'evaluation_id' => $er->evaluation_id,
                    'work_type' => 'evaluation',
                    'title' => $evaluation?->title ?? 'Evaluation Work',
                    'status' => 'graded',
                    'score' => min($scaledScore, 20),
                    'max_score' => 20,
                    'submitted_at' => $er->completed_at,
                    'graded_at' => $er->completed_at,
                ]);
                $created++;
            }
        }

        // From completed exam attempts
        $examAttempts = ExamAttempt::where('status', 'completed')
            ->whereIn('exam_id', $teacherExamIds)
            ->get();

        foreach ($examAttempts as $ea) {
            $exists = SubmittedWork::where('student_id', $ea->student_id)
                ->where('exam_id', $ea->exam_id)
                ->where('work_type', 'exam')
                ->exists();

            if (!$exists) {
                $exam = \App\Models\Exam::find($ea->exam_id);
                $scaledScore = $ea->total_points > 0 ? (int) round(($ea->score / $ea->total_points) * 20) : 0;
                SubmittedWork::create([
                    'student_id' => $ea->student_id,
                    'exam_id' => $ea->exam_id,
                    'work_type' => 'exam',
                    'title' => $exam?->title ?? 'Exam Work',
                    'status' => 'graded',
                    'score' => min($scaledScore, 20),
                    'max_score' => 20,
                    'submitted_at' => $ea->completed_at,
                    'graded_at' => $ea->completed_at,
                ]);
                $created++;
            }
        }

        return response()->json([
            'message' => "Generated {$created} submitted work records.",
            'created' => $created,
        ]);
    }
}
