<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;
use App\Models\Lesson;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\LessonProgress;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class ProgressApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';
    private User $student;
    private User $teacher;
    private User $admin;
    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $studentRole = Role::firstOrCreate(['name' => 'student']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $this->teacher = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Teacher Progress',
            'email' => 'teacher.progress@test.com',
            'password' => 'password123',
            'role_id' => $teacherRole->id,
            'is_active' => true,
            'provider' => 'email',
        ]);

        TeacherProfile::create([
            'id' => Str::uuid(),
            'user_id' => $this->teacher->id,
            'department' => 'Mathematics',
            'specialization' => 'Algebra',
            'years_experience' => 5,
            'students_count' => 30,
        ]);

        $this->student = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Student Progress',
            'email' => 'student.progress@test.com',
            'password' => 'password123',
            'role_id' => $studentRole->id,
            'is_active' => true,
            'provider' => 'email',
        ]);

        StudentProfile::create([
            'id' => Str::uuid(),
            'user_id' => $this->student->id,
            'academic_level' => 'intermediate',
            'total_lessons_completed' => 3,
            'average_score' => 16.5,
            'total_time_spent' => 3600,
            'current_streak' => 5,
            'badges' => ['first_lesson'],
        ]);

        $this->admin = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Admin Progress',
            'email' => 'admin.progress@test.com',
            'password' => 'password123',
            'role_id' => $adminRole->id,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->lesson = Lesson::create([
            'id' => Str::uuid(),
            'title' => 'Algebra Básica',
            'description' => 'Introducción al álgebra',
            'content' => '<p>Contenido de álgebra</p>',
            'teacher_id' => $this->teacher->id,
            'unit' => 1,
            'topic' => 'Algebra',
            'difficulty' => 'basic',
            'is_published' => true,
            'published_at' => now(),
            'estimated_time' => 30,
            'order' => 1,
        ]);
    }

    public function test_student_can_get_dashboard(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $response = $this->getJson("{$this->baseUrl}/dashboard/student");

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'full_name', 'email', 'role'],
                'profile',
                'stats' => [
                    'total_lessons',
                    'completed_lessons',
                    'in_progress_lessons',
                    'average_score',
                    'current_streak',
                    'total_time_spent',
                    'badges',
                ],
                'in_progress_lessons',
                'completed_lessons',
                'recent_evaluations',
                'upcoming_evaluations',
            ]);
    }

    public function test_student_can_get_stats(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $response = $this->getJson("{$this->baseUrl}/dashboard/student/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'full_name', 'email', 'role'],
                'stats' => [
                    'total_lessons',
                    'completed_lessons',
                    'in_progress_lessons',
                    'average_score',
                    'current_streak',
                    'total_time_spent',
                    'badges_count',
                    'pending_evaluations',
                    'completed_evaluations',
                ],
            ])
            ->assertJsonPath('stats.current_streak', 5)
            ->assertJsonPath('stats.average_score', 16.5);
    }

    public function test_student_can_update_lesson_progress(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $response = $this->postJson("{$this->baseUrl}/lessons/{$this->lesson->id}/progress", [
            'progress' => 50,
            'time_spent' => 300,
            'last_position' => 5,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'progress' => ['id', 'user_id', 'lesson_id', 'progress', 'status', 'time_spent'],
                'badges',
            ])
            ->assertJsonPath('progress.progress', 50)
            ->assertJsonPath('progress.status', 'in_progress');

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'progress' => 50,
            'status' => 'in_progress',
        ]);
    }

    public function test_student_can_get_badges(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $response = $this->getJson("{$this->baseUrl}/progress/badges");

        $response->assertOk()
            ->assertJsonStructure([
                'badges' => [
                    '*' => ['id', 'name', 'description', 'icon', 'unlocked'],
                ],
                'unlocked_count',
                'total_badges',
            ])
            ->assertJsonPath('unlocked_count', 1)
            ->assertJsonPath('total_badges', 6);
    }

    public function test_student_can_get_streak(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $response = $this->getJson("{$this->baseUrl}/dashboard/student");

        $response->assertOk()
            ->assertJsonPath('profile.current_streak', 5)
            ->assertJsonPath('stats.current_streak', 5);
    }

    public function test_teacher_can_get_progress(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->getJson("{$this->baseUrl}/dashboard/teacher");

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'full_name', 'email', 'role'],
                'stats' => [
                    'total_students',
                    'total_lessons',
                    'total_evaluations',
                    'published_lessons',
                    'published_evaluations',
                    'total_submissions',
                    'average_score',
                    'pending_reviews',
                ],
                'recent_students',
                'recent_evaluations',
            ]);
    }

    public function test_unauthenticated_cannot_access_progress(): void
    {
        $response = $this->getJson("{$this->baseUrl}/dashboard/student");
        $this->assertTrue($response->status() >= 400, 'Unauthenticated user should not access dashboard');

        $response = $this->getJson("{$this->baseUrl}/progress/my-stats");
        $this->assertTrue($response->status() >= 400, 'Unauthenticated user should not access stats');

        $response = $this->getJson("{$this->baseUrl}/progress/badges");
        $this->assertTrue($response->status() >= 400, 'Unauthenticated user should not access badges');
    }

    public function test_student_can_get_upcoming_evaluations(): void
    {
        $evaluation = Evaluation::create([
            'id' => Str::uuid(),
            'title' => 'Examen de Álgebra',
            'description' => 'Evaluación sobre álgebra lineal',
            'teacher_id' => $this->teacher->id,
            'lesson_id' => $this->lesson->id,
            'type' => 'exam',
            'difficulty' => 'basic',
            'time_limit' => 60,
            'due_date' => now()->addDays(7),
            'is_published' => true,
            'published_at' => now(),
            'auto_correct' => true,
            'randomize_questions' => false,
            'max_attempts' => 3,
            'total_questions' => 10,
            'total_points' => 20,
        ]);

        Sanctum::actingAs($this->student, ['web']);

        $response = $this->getJson("{$this->baseUrl}/dashboard/student");

        $response->assertOk()
            ->assertJsonStructure([
                'upcoming_evaluations',
            ]);

        $upcoming = $response->json('upcoming_evaluations');
        $this->assertNotEmpty($upcoming);
        $this->assertEquals($evaluation->id, $upcoming[0]['id']);
    }
}
