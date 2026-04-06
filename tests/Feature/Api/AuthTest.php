<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiTestCase;

class AuthTest extends ApiTestCase
{
    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_login_with_valid_credentials_returns_token_and_user(): void
    {
        $dept  = $this->createDepartment();
        $level = $this->createLevel();
        User::factory()->create([
            'email'         => 'admin@test.com',
            'password'      => Hash::make('Secret@123'),
            'department_id' => $dept->id,
            'user_level_id' => $level->id,
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'admin@test.com',
            'password' => 'Secret@123',
        ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'is_admin'],
            ]);
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        $dept  = $this->createDepartment();
        $level = $this->createLevel();
        User::factory()->create([
            'email'         => 'user@test.com',
            'password'      => Hash::make('CorrectPass'),
            'department_id' => $dept->id,
            'user_level_id' => $level->id,
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'user@test.com',
            'password' => 'WrongPass',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_non_existent_email(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'nobody@nowhere.com',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_login_rejects_malformed_email(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'not-an-email',
            'password' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_logout_succeeds_for_authenticated_user(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully.']);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }

    // ── Me ────────────────────────────────────────────────────────────────────

    public function test_me_returns_the_authenticated_user(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $admin->email, 'is_admin' => true]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_me_returns_department_and_level_relations(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['department', 'level']]);
    }
}
