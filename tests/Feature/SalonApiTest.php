<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Role;
use App\Models\Salon;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Enrollment;
use App\Models\AcademicPeriod;
use Laravel\Sanctum\Sanctum;

class SalonApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';
    private User $coordinator;
    private User $teacher;
    private User $teacher2;
    private User $student;
    private User $otherStudent;
    private User $admin;
    private Salon $salon;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $coordRole = Role::firstOrCreate(['name' => 'coordinador']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher']);
        $studentRole = Role::firstOrCreate(['name' => 'student']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $this->coordinator = $this->makeUser('Coord Salon', 'coord-salon@test.com', $coordRole);
        $this->teacher = $this->makeUser('Teacher Salon', 'teacher-salon@test.com', $teacherRole);
        $this->teacher2 = $this->makeUser('Teacher Salon 2', 'teacher2-salon@test.com', $teacherRole);
        $this->student = $this->makeUser('Student Salon', 'student-salon@test.com', $studentRole);
        $this->otherStudent = $this->makeUser('Other Salon', 'other-salon@test.com', $studentRole);
        $this->admin = $this->makeUser('Admin Salon', 'admin-salon@test.com', $adminRole);

        $period = AcademicPeriod::create([
            'id' => Str::uuid(),
            'name' => '2026-A',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'is_active' => true,
        ]);

        $this->salon = Salon::create([
            'id' => Str::uuid(),
            'grade' => '5to',
            'section' => 'A',
            'academic_period_id' => $period->id,
            'coordinator_id' => $this->coordinator->id,
        ]);

        $this->course = Course::create([
            'id' => Str::uuid(),
            'salon_id' => $this->salon->id,
            'name' => 'Matematica',
            'description' => 'Curso de matematica',
            'teacher_id' => $this->teacher->id,
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

    // ===== SALONES CRUD =====

    public function test_teacher_can_list_its_salons()
    {
        $this->actingAsUser($this->teacher);

        $this->getJson("$this->baseUrl/salones")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['grade' => '5to']);
    }

    public function test_teacher_not_assigned_gets_empty_salons()
    {
        $this->actingAsUser($this->teacher2);

        $this->getJson("$this->baseUrl/salones")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_coordinator_can_list_all_salons()
    {
        $this->actingAsUser($this->coordinator);

        $this->getJson("$this->baseUrl/salones")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_coordinator_can_create_salon()
    {
        $this->actingAsUser($this->coordinator);

        $this->postJson("$this->baseUrl/salones", ['grade' => '6to', 'section' => 'B'])
            ->assertStatus(201)
            ->assertJsonPath('data.grade', '6to');

        $this->assertDatabaseHas('salones', ['grade' => '6to', 'section' => 'B']);
    }

    public function test_admin_cannot_create_salon()
    {
        $this->actingAsUser($this->admin);

        $this->postJson("$this->baseUrl/salones", ['grade' => '7to', 'section' => 'C'])
            ->assertStatus(403);
    }

    public function test_student_cannot_create_salon()
    {
        $this->actingAsUser($this->student);

        $this->postJson("$this->baseUrl/salones", ['grade' => '7to', 'section' => 'C'])
            ->assertStatus(403);
    }

    // ===== CURSOS =====

    public function test_coordinator_can_create_course_in_salon()
    {
        $this->actingAsUser($this->coordinator);

        $this->postJson("$this->baseUrl/salones/{$this->salon->id}/courses", [
            'name' => 'Fisica',
            'teacher_id' => $this->teacher2->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Fisica');
    }

    public function test_teacher_can_only_see_its_courses_in_salon()
    {
        // Curso del teacher2 en el mismo salón
        Course::create([
            'id' => Str::uuid(),
            'salon_id' => $this->salon->id,
            'name' => 'Fisica',
            'teacher_id' => $this->teacher2->id,
        ]);

        $this->actingAsUser($this->teacher);

        $this->getJson("$this->baseUrl/salones/{$this->salon->id}/courses")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Matematica']);
    }

    public function test_student_cannot_create_course()
    {
        $this->actingAsUser($this->student);

        $this->postJson("$this->baseUrl/salones/{$this->salon->id}/courses", [
            'name' => 'Fisica',
            'teacher_id' => $this->teacher2->id,
        ])->assertStatus(403);
    }

    // ===== LECCIONES =====

    public function test_teacher_can_create_lesson_in_its_course()
    {
        $this->actingAsUser($this->teacher);

        $this->postJson("$this->baseUrl/courses/{$this->course->id}/lessons", [
            'title' => 'Teorema de Pitagoras',
            'content' => 'Contenido de la leccion',
        ])->assertStatus(201);

        $this->assertDatabaseHas('lessons', ['title' => 'Teorema de Pitagoras']);
    }

    public function test_teacher_not_owning_course_cannot_create_lesson()
    {
        $this->actingAsUser($this->teacher2);

        $this->postJson("$this->baseUrl/courses/{$this->course->id}/lessons", [
            'title' => 'Intrusion',
            'content' => 'x',
        ])->assertStatus(403);
    }

    // ===== MATRÍCULA =====

    public function test_teacher_can_enroll_student_in_its_course()
    {
        $this->actingAsUser($this->teacher);

        $this->postJson("$this->baseUrl/courses/{$this->course->id}/enroll", [
            'student_ids' => [$this->student->id],
        ])->assertOk();

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
        ]);
    }

    public function test_teacher_not_owning_course_cannot_enroll()
    {
        $this->actingAsUser($this->teacher2);

        $this->postJson("$this->baseUrl/courses/{$this->course->id}/enroll", [
            'student_ids' => [$this->student->id],
        ])->assertStatus(403);
    }

    // ===== ESTUDIANTE =====

    public function test_enrolled_student_sees_course()
    {
        Enrollment::create([
            'id' => Str::uuid(),
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
            'enrolled_by' => $this->teacher->id,
        ]);

        $this->actingAsUser($this->student);

        $this->getJson("$this->baseUrl/salones")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Matematica']);
    }

    public function test_enrolled_student_can_see_lessons_of_course()
    {
        Enrollment::create([
            'id' => Str::uuid(),
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
            'enrolled_by' => $this->teacher->id,
        ]);

        Lesson::create([
            'id' => Str::uuid(),
            'course_id' => $this->course->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Pitagoras',
            'content' => 'Contenido',
            'is_published' => true,
            'order' => 1,
        ]);

        $this->actingAsUser($this->student);

        $this->getJson("$this->baseUrl/courses/{$this->course->id}")
            ->assertOk()
            ->assertJsonCount(1, 'lessons');
    }

    public function test_non_enrolled_student_cannot_access_course_detail()
    {
        $this->actingAsUser($this->otherStudent);

        $this->getJson("$this->baseUrl/courses/{$this->course->id}")
            ->assertStatus(403);
    }

    public function test_admin_cannot_access_course_detail()
    {
        $this->actingAsUser($this->admin);

        $this->getJson("$this->baseUrl/courses/{$this->course->id}")
            ->assertStatus(403);
    }

    // ===== CATÁLOGOS =====

    public function test_coordinator_can_list_teachers_and_students()
    {
        $this->actingAsUser($this->coordinator);

        $this->getJson("$this->baseUrl/catalog/teachers")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("$this->baseUrl/catalog/students")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_teacher_cannot_access_catalogs()
    {
        $this->actingAsUser($this->teacher);

        $this->getJson("$this->baseUrl/catalog/teachers")->assertStatus(403);
        $this->getJson("$this->baseUrl/catalog/students")->assertStatus(403);
    }
}