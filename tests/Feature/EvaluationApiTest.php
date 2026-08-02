<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;
use App\Models\Evaluation;
use App\Models\Question;
use Laravel\Sanctum\Sanctum;

class EvaluationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';
    private User $teacher;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $teacherRole = Role::firstOrCreate(['name' => 'teacher']);
        $studentRole = Role::firstOrCreate(['name' => 'student']);

        $this->teacher = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'full_name' => 'Teacher Test',
            'email' => 'teacher-eval@test.com',
            'password' => 'password123',
            'role_id' => $teacherRole->id,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->student = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'full_name' => 'Student Test',
            'email' => 'student-eval@test.com',
            'password' => 'password123',
            'role_id' => $studentRole->id,
            'is_active' => true,
            'provider' => 'email',
        ]);
    }

    public function test_teacher_can_list_evaluations(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Midterm Exam',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_EXAM,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => true,
        ]);

        $response = $this->getJson("{$this->baseUrl}/evaluations");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'title', 'type', 'difficulty'],
                    ],
                ],
            ]);
    }

    public function test_student_can_list_evaluations(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Published Quiz',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_INTERMEDIATE,
            'is_published' => true,
        ]);

        Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Draft Quiz',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => false,
        ]);

        $response = $this->getJson("{$this->baseUrl}/evaluations");

        $response->assertOk()->assertJsonStructure(['success', 'data']);
    }

    public function test_teacher_can_create_evaluation(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $payload = [
            'title' => 'New Quiz',
            'description' => 'A test quiz',
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'time_limit' => 30,
        ];

        $response = $this->postJson("{$this->baseUrl}/evaluations", $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'title', 'type', 'difficulty', 'teacher_id'],
            ])
            ->assertJsonPath('data.title', 'New Quiz')
            ->assertJsonPath('data.type', Evaluation::TYPE_QUIZ);
    }

    public function test_student_cannot_create_evaluation(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $payload = [
            'title' => 'Unauthorized Quiz',
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
        ];

        $response = $this->postJson("{$this->baseUrl}/evaluations", $payload);

        $response->assertStatus(403);
    }

    public function test_teacher_can_update_evaluation(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Old Title',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_EXAM,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => false,
        ]);

        $response = $this->putJson("{$this->baseUrl}/evaluations/{$evaluation->id}", [
            'title' => 'Updated Title',
            'difficulty' => Evaluation::DIFFICULTY_ADVANCED,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.difficulty', Evaluation::DIFFICULTY_ADVANCED);
    }

    public function test_teacher_can_add_question(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Quiz With Questions',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => false,
        ]);

        $payload = [
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'question_text' => 'What is 2+2?',
            'options' => [
                ['label' => 'A', 'value' => '3'],
                ['label' => 'B', 'value' => '4'],
                ['label' => 'C', 'value' => '5'],
                ['label' => 'D', 'value' => '6'],
            ],
            'correct_answer' => '4',
            'points' => 5,
            'order' => 0,
        ];

        $response = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/questions", $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'type', 'question_text', 'correct_answer', 'points'],
            ])
            ->assertJsonPath('data.question_text', 'What is 2+2?')
            ->assertJsonPath('data.points', 5);
    }

    public function test_teacher_can_publish_evaluation(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Ready To Publish',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_EXAM,
            'difficulty' => Evaluation::DIFFICULTY_INTERMEDIATE,
            'is_published' => false,
        ]);

        Question::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'type' => Question::TYPE_FILL_BLANK,
            'question_text' => 'Solve: x + 3 = 7',
            'correct_answer' => '4',
            'points' => 5,
            'order' => 0,
        ]);

        $response = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/publish");

        $response->assertOk()
            ->assertJsonPath('data.is_published', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'is_published'],
            ]);
    }

    public function test_cannot_publish_without_questions(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Empty Evaluation',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => false,
        ]);

        $response = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/publish");

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_student_can_submit_evaluation(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Submittable Quiz',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => true,
            'max_attempts' => 3,
            'time_limit' => 30,
        ]);

        $question = Question::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'question_text' => 'What is 3 x 3?',
            'options' => [
                ['label' => 'A', 'value' => '6'],
                ['label' => 'B', 'value' => '9'],
                ['label' => 'C', 'value' => '12'],
            ],
            'correct_answer' => '9',
            'points' => 10,
            'order' => 0,
        ]);

        Sanctum::actingAs($this->student, ['web']);

        $response = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/submit", [
            'answers' => [
                ['question_id' => $question->id, 'answer' => '9'],
            ],
            'time_taken' => 600,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'result' => ['id', 'score', 'status'],
                    'score',
                    'correct_answers',
                    'total_questions',
                ],
            ])
            ->assertJsonPath('data.total_questions', 1);
    }

    public function test_teacher_can_view_results(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Evaluated Quiz',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_EXAM,
            'difficulty' => Evaluation::DIFFICULTY_ADVANCED,
            'is_published' => true,
        ]);

        $question = Question::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'type' => Question::TYPE_FORMULA,
            'question_text' => 'Calculate 5 + 5',
            'correct_answer' => '10',
            'points' => 10,
            'order' => 0,
        ]);

        Sanctum::actingAs($this->student, ['web']);

        $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/submit", [
            'answers' => [
                ['question_id' => $question->id, 'answer' => '10'],
            ],
            'time_taken' => 300,
        ]);

        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->getJson("{$this->baseUrl}/evaluations/{$evaluation->id}/results");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'stats' => [
                    'total_submissions',
                    'average_score',
                    'max_score',
                    'min_score',
                    'passing_rate',
                ],
            ]);
    }
}
