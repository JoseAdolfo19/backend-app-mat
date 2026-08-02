<?php

namespace App\Exports;

use App\Models\User;
use App\Models\LessonProgress;
use App\Models\EvaluationResult;
use Illuminate\Support\Facades\DB;

class StudentProgressPDFExport
{
    protected $studentId;

    public function __construct($studentId)
    {
        $this->studentId = $studentId;
    }

    public function getData()
    {
        $student = User::find($this->studentId);

        $lessons = LessonProgress::where('student_id', $this->studentId)
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->select('lessons.title', 'lessons.unit', 'lesson_progress.status', 'lesson_progress.progress_percentage', 'lesson_progress.updated_at')
            ->orderBy('lessons.unit')
            ->get();

        $evaluations = DB::table('evaluation_results')
            ->join('evaluations', 'evaluation_results.evaluation_id', '=', 'evaluations.id')
            ->join('users', 'evaluation_results.student_id', '=', 'users.id')
            ->select('evaluations.title', 'evaluation_results.score', 'evaluation_results.correct_answers', 'evaluation_results.total_questions', 'evaluation_results.created_at')
            ->where('evaluation_results.student_id', $this->studentId)
            ->orderBy('evaluation_results.created_at', 'desc')
            ->get();

        return [
            'student' => $student,
            'lessons' => $lessons,
            'evaluations' => $evaluations,
            'stats' => [
                'lessons_completed' => $lessons->where('status', 'completed')->count(),
                'lessons_in_progress' => $lessons->where('status', 'in_progress')->count(),
                'average_score' => $evaluations->avg('score') ?? 0,
                'total_evaluations' => $evaluations->count(),
            ]
        ];
    }
}
