<?php

namespace Database\Seeders;

use App\Models\SubmittedWork;
use App\Models\LessonProgress;
use App\Models\EvaluationResult;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Evaluation;
use App\Models\Exam;
use Illuminate\Database\Seeder;

class RankingSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;

        $completedLessons = LessonProgress::where('status', 'completed')->get();

        foreach ($completedLessons as $lp) {
            $exists = SubmittedWork::where('student_id', $lp->user_id)
                ->where('lesson_id', $lp->lesson_id)
                ->where('work_type', 'lesson')
                ->exists();

            if (!$exists) {
                $lesson = Lesson::find($lp->lesson_id);
                SubmittedWork::create([
                    'student_id' => $lp->user_id,
                    'lesson_id' => $lp->lesson_id,
                    'work_type' => 'lesson',
                    'title' => $lesson?->title ?? 'Lesson Work',
                    'status' => 'graded',
                    'score' => rand(10, 20),
                    'max_score' => 20,
                    'submitted_at' => $lp->completed_at ?? $lp->updated_at,
                    'graded_at' => $lp->completed_at ?? $lp->updated_at,
                ]);
                $created++;
            }
        }

        $evalResults = EvaluationResult::where('status', 'completed')->get();

        foreach ($evalResults as $er) {
            $exists = SubmittedWork::where('student_id', $er->user_id)
                ->where('evaluation_id', $er->evaluation_id)
                ->where('work_type', 'evaluation')
                ->exists();

            if (!$exists) {
                $evaluation = Evaluation::find($er->evaluation_id);
                $scaledScore = $er->max_score > 0 ? (int) round(($er->score / $er->max_score) * 20) : rand(10, 20);
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

        $examAttempts = ExamAttempt::where('status', 'completed')->get();

        foreach ($examAttempts as $ea) {
            $exists = SubmittedWork::where('student_id', $ea->student_id)
                ->where('exam_id', $ea->exam_id)
                ->where('work_type', 'exam')
                ->exists();

            if (!$exists) {
                $exam = Exam::find($ea->exam_id);
                $scaledScore = $ea->total_points > 0 ? (int) round(($ea->score / $ea->total_points) * 20) : rand(10, 20);
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

        $this->command->info("RankingSeeder: Created {$created} submitted work records.");
    }
}
