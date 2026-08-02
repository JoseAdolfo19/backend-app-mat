<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;
use App\Models\Lesson;
use App\Models\Evaluation;
use Laravel\Sanctum\Sanctum;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';

    public function test_can_register_user(): void
    {
        $studentRole = Role::where('name', 'student')->firstOrCreate(
            ['id' => \Illuminate\Support\Str::uuid(), 'name' => 'student']
        );

        $response = $this->postJson("{$this->baseUrl}/auth/register", [
            'full_name' => 'Test Student',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student',
            'academic_level' => 'basic',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'access_token', 'token_type']);
    }

    public function test_can_login(): void
    {
        $role = Role::firstOrCreate(['name' => 'student']);
        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'email' => 'login@example.com',
            'full_name' => 'Login Test',
            'password' => 'password123',
            'role_id' => $role->id,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $response = $this->postJson("{$this->baseUrl}/auth/login", [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'access_token']);
    }

    public function test_cannot_login_with_wrong_password(): void
    {
        $role = Role::firstOrCreate(['name' => 'student']);
        User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'email' => 'wrong@example.com',
            'full_name' => 'Wrong Test',
            'password' => 'password123',
            'role_id' => $role->id,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $response = $this->postJson("{$this->baseUrl}/auth/login", [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_cannot_access_profile(): void
    {
        $response = $this->getJson("{$this->baseUrl}/user/profile");
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $role = Role::firstOrCreate(['name' => 'student']);
        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'email' => 'profile@example.com',
            'full_name' => 'Profile Test',
            'password' => 'password123',
            'role_id' => $role->id,
            'is_active' => true,
            'provider' => 'email',
        ]);

        Sanctum::actingAs($user, ['web']);

        $response = $this->getJson("{$this->baseUrl}/user/profile");
        $response->assertOk()
            ->assertJsonPath('user.email', 'profile@example.com');
    }

    public function test_health_check(): void
    {
        $response = $this->getJson("{$this->baseUrl}/health");
        $response->assertOk()
            ->assertJsonPath('status', 'healthy');
    }
}
