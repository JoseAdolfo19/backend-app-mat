<?php

namespace App\Exports;

use App\Models\EvaluationResult;
use App\Models\Evaluation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GradesPDFExport
{
    protected $teacherId;
    protected $filters;

    public function __construct($teacherId = null, $filters = [])
    {
        $this->teacherId = $teacherId;
        $this->filters = $filters;
    }

    public function getData()
    {
        $query = DB::table('evaluation_results')
            ->join('evaluations', 'evaluation_results.evaluation_id', '=', 'evaluations.id')
            ->join('users', 'evaluation_results.student_id', '=', 'users.id')
            ->leftJoin('lessons', 'evaluations.lesson_id', '=', 'lessons.id')
            ->select(
                'users.full_name as student_name',
                'users.email as student_email',
                'evaluations.title as evaluation_title',
                'lessons.title as lesson_title',
                'lessons.unit as area',
                'evaluation_results.score',
                'evaluation_results.total_questions',
                'evaluation_results.correct_answers',
                'evaluation_results.created_at as completed_at'
            );

        if ($this->teacherId) {
            $query->where('evaluations.teacher_id', $this->teacherId);
        }

        if (!empty($this->filters['student_id'])) {
            $query->where('evaluation_results.student_id', $this->filters['student_id']);
        }

        if (!empty($this->filters['evaluation_id'])) {
            $query->where('evaluation_results.evaluation_id', $this->filters['evaluation_id']);
        }

        if (!empty($this->filters['area'])) {
            $query->where('lessons.unit', $this->filters['area']);
        }

        return $query->orderBy('evaluation_results.created_at', 'desc')->get();
    }

    public function getSummary()
    {
        $data = $this->getData();

        $summary = [
            'total_results' => $data->count(),
            'average_score' => $data->avg('score') ?? 0,
            'max_score' => $data->max('score') ?? 0,
            'min_score' => $data->min('score') ?? 0,
            'by_area' => $data->groupBy('area')->map(function ($items, $area) {
                return [
                    'area' => $area,
                    'count' => $items->count(),
                    'average' => $items->avg('score'),
                ];
            })->values(),
        ];

        return $summary;
    }
}
