<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Role;
use App\Models\Salon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Game;
use App\Models\GameSubmission;
use App\Models\AcademicPeriod;
use Laravel\Sanctum\Sanctum;

class GamesApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';
    private User $teacher;
    private User $teacher2;
    private User $student;
    private User $student2;
    private User $admin;
    private Course $course;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $coordRole = Role::firstOrCreate(['name' => 'coordinador']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher']);
        $studentRole = Role::firstOrCreate(['name' => 'student']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $this->teacher = $this->makeUser('Teacher Game', 'teacher-game@test.com', $teacherRole);
        $this->teacher2 = $this->makeUser('Teacher Game 2', 'teacher2-game@test.com', $teacherRole);
        $this->student = $this->makeUser('Student Game', 'student-game@test.com', $studentRole);
        $this->student2 = $this->makeUser('Student Game 2', 'student2-game@test.com', $studentRole);
        $this->admin = $this->makeUser('Admin Game', 'admin-game@test.com', $adminRole);

        $period = AcademicPeriod::create([
            'id' => Str::uuid(),
            'name' => '2026-A',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'is_active' => true,
        ]);

        $salon = Salon::create([
            'id' => Str::uuid(),
            'grade' => '5to',
            'section' => 'A',
            'academic_period_id' => $period->id,
            'coordinator_id' => null,
        ]);

        $this->course = Course::create([
            'id' => Str::uuid(),
            'salon_id' => $salon->id,
            'name' => 'Matematica',
            'teacher_id' => $this->teacher->id,
        ]);

        $this->game = Game::create([
            'id' => Str::uuid(),
            'course_id' => $this->course->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Quiz de fracciones',
            'platform' => 'quizizz',
            'is_active' => true,
        ]);
    }

    private function makeUser(string $name, string $email, Role $role): User
    {
        return User::create([
            'id' => Str::uuid(),
            'full_name' => $name,
            'email' => $email,
            'password' => 'password123',
            'role_id' => $role->id,
            'is_active' => true,
            'provider' => 'email',
        ]);
    }

    private function actingAsUser(User $user): void
    {
        Sanctum::actingAs($user);
    }

    private function enroll(User $student): void
    {
        Enrollment::create([
            'id' => Str::uuid(),
            'course_id' => $this->course->id,
            'student_id' => $student->id,
            'enrolled_by' => $this->teacher->id,
        ]);
    }

    // ===== CRUD =====

    public function test_teacher_can_create_game()
    {
        $this->actingAsUser($this->teacher);

        $this->postJson("$this->baseUrl/games", [
            'course_id' => $this->course->id,
            'title' => 'Kahoot de algebra',
            'pin' => '123456',
            'platform' => 'kahoot',
        ])->assertStatus(201)->assertJsonPath('data.title', 'Kahoot de algebra');

        $this->assertDatabaseHas('games', ['title' => 'Kahoot de algebra']);
    }

    public function test_teacher_not_owning_course_cannot_create_game()
    {
        $this->actingAsUser($this->teacher2);

        $this->postJson("$this->baseUrl/games", [
            'course_id' => $this->course->id,
            'title' => 'Intrusion',
        ])->assertStatus(403);
    }

    public function test_student_cannot_create_game()
    {
        $this->actingAsUser($this->student);

        $this->postJson("$this->baseUrl/games", [
            'course_id' => $this->course->id,
            'title' => 'X',
        ])->assertStatus(403);
    }

    public function test_teacher_can_update_and_delete_own_game()
    {
        $this->actingAsUser($this->teacher);

        $this->putJson("$this->baseUrl/games/{$this->game->id}", ['title' => 'Renombrado'])
            ->assertOk()->assertJsonPath('data.title', 'Renombrado');

        $this->deleteJson("$this->baseUrl/games/{$this->game->id}")->assertOk();
        $this->assertDatabaseMissing('games', ['id' => $this->game->id]);
    }

    public function test_other_teacher_cannot_modify_game()
    {
        $this->actingAsUser($this->teacher2);

        $this->putJson("$this->baseUrl/games/{$this->game->id}", ['title' => 'X'])->assertStatus(403);
    }

    // ===== LISTADO =====

    public function test_student_sees_only_games_of_enrolled_courses()
    {
        $this->enroll($this->student);

        $this->actingAsUser($this->student);

        $this->getJson("$this->baseUrl/games")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Quiz de fracciones']);
    }

    public function test_non_enrolled_student_sees_no_games()
    {
        $this->actingAsUser($this->student2);

        $this->getJson("$this->baseUrl/games")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ===== COMPROBANTE =====

    public function test_enrolled_student_can_submit_score()
    {
        $this->enroll($this->student);
        $this->actingAsUser($this->student);

        $this->postJson("$this->baseUrl/games/{$this->game->id}/submit", ['score' => '1250'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_non_enrolled_student_cannot_submit()
    {
        $this->actingAsUser($this->student2);

        $this->postJson("$this->baseUrl/games/{$this->game->id}/submit", ['score' => '500'])
            ->assertStatus(403);
    }

    public function test_submit_requires_score_or_screenshot()
    {
        $this->enroll($this->student);
        $this->actingAsUser($this->student);

        $this->postJson("$this->baseUrl/games/{$this->game->id}/submit", [])
            ->assertStatus(422);
    }

    // ===== CALIFICACIÓN + XP =====

    public function test_teacher_can_approve_and_award_xp()
    {
        $this->enroll($this->student);

        $submission = GameSubmission::create([
            'id' => Str::uuid(),
            'game_id' => $this->game->id,
            'student_id' => $this->student->id,
            'score' => '1500',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->actingAsUser($this->teacher);

        $this->postJson("$this->baseUrl/games/submissions/{$submission->id}/grade", [
            'status' => 'approved',
            'grade' => 20,
        ])->assertOk()->assertJsonPath('xp_awarded', 40);

        $this->assertDatabaseHas('game_submissions', ['id' => $submission->id, 'status' => 'approved', 'xp_awarded' => 40]);
    }

    public function test_teacher_not_owning_cannot_grade()
    {
        $this->enroll($this->student);

        $submission = GameSubmission::create([
            'id' => Str::uuid(),
            'game_id' => $this->game->id,
            'student_id' => $this->student->id,
            'score' => '1500',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->actingAsUser($this->teacher2);

        $this->postJson("$this->baseUrl/games/submissions/{$submission->id}/grade", [
            'status' => 'approved',
            'grade' => 20,
        ])->assertStatus(403);
    }

    public function test_rejected_submission_awards_no_xp()
    {
        $this->enroll($this->student);

        $submission = GameSubmission::create([
            'id' => Str::uuid(),
            'game_id' => $this->game->id,
            'student_id' => $this->student->id,
            'score' => '1500',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->actingAsUser($this->teacher);

        $this->postJson("$this->baseUrl/games/submissions/{$submission->id}/grade", [
            'status' => 'rejected',
        ])->assertOk()->assertJsonPath('xp_awarded', 0);

        $this->assertDatabaseHas('game_submissions', ['id' => $submission->id, 'status' => 'rejected', 'xp_awarded' => 0]);
    }

    // ===== CATÁLOGO CURSOS =====

    public function test_teacher_can_list_its_courses_for_assignment()
    {
        $this->actingAsUser($this->teacher);

        $this->getJson("$this->baseUrl/games/teacher-courses")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_student_cannot_list_teacher_courses()
    {
        $this->actingAsUser($this->student);

        $this->getJson("$this->baseUrl/games/teacher-courses")->assertStatus(403);
    }
}