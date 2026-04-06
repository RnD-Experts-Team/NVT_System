<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserLevel;
use Spatie\Permission\Models\Role;
use Tests\Feature\ApiTestCase;

class UserTest extends ApiTestCase
{
    // ── Access Control ────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_list_users(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertStatus(403);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_admin_can_list_all_users(): void
    {
        $admin = $this->createAdmin();
        $this->createUser();

        // admin + 1 new user = 2
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/users')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_list_includes_relationships(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'department', 'level']]]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_a_user(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Engineering');
        $level = $this->createLevel('L2', 2);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users/create', [
                'name'          => 'Jane Doe',
                'ac_no'         => '5001',
                'email'         => 'jane@test.com',
                'password'      => 'Secret@1234',
                'department_id' => $dept->id,
                'user_level_id' => $level->id,
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['email' => 'jane@test.com']);

        $this->assertDatabaseHas('users', ['email' => 'jane@test.com']);
    }

    public function test_creating_user_auto_assigns_spatie_role_matching_level_code(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Dept');
        $level = $this->createLevel('L2', 2);

        // The role 'L2' must exist for syncRoles to work
        Role::firstOrCreate(['name' => 'L2', 'guard_name' => 'web']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users/create', [
                'name'          => 'Bob',
                'ac_no'         => '5002',
                'email'         => 'bob@test.com',
                'password'      => 'Secret@1234',
                'department_id' => $dept->id,
                'user_level_id' => $level->id,
            ])
            ->assertStatus(201);

        $user = User::where('email', 'bob@test.com')->first();
        $this->assertTrue($user->hasRole('L2'));
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users/create', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'department_id', 'user_level_id']);
    }

    public function test_store_requires_unique_email(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Dept');
        $level = $this->createLevel('L2', 2);

        User::factory()->create([
            'email'         => 'taken@test.com',
            'department_id' => $dept->id,
            'user_level_id' => $level->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users/create', [
                'name'          => 'Duplicate',
                'email'         => 'taken@test.com',
                'password'      => 'Secret@1234',
                'department_id' => $dept->id,
                'user_level_id' => $level->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_requires_password_of_minimum_8_chars(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Dept');
        $level = $this->createLevel('L2', 2);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users/create', [
                'name'          => 'Short',
                'email'         => 'short@test.com',
                'password'      => '1234',
                'department_id' => $dept->id,
                'user_level_id' => $level->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_store_rejects_non_existent_department(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createLevel('L2', 2);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users/create', [
                'name'          => 'Ghost',
                'email'         => 'ghost@test.com',
                'password'      => 'Secret@1234',
                'department_id' => 99999,
                'user_level_id' => $level->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['department_id']);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_admin_can_show_a_user(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/users/' . $user->id)
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email])
            ->assertJsonStructure(['data' => ['department', 'level', 'roles']]);
    }

    public function test_show_returns_404_for_unknown_user(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/users/99999')
            ->assertStatus(404);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_user_name(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/users/' . $user->id . '/update', ['name' => 'Updated Name'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_update_syncs_spatie_role_when_level_changes(): void
    {
        $admin  = $this->createAdmin();
        $dept   = $this->createDepartment('Dept');
        $level1 = $this->createLevel('L1', 1);
        $level2 = $this->createLevel('L2', 2);

        Role::firstOrCreate(['name' => 'L1', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'L2', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'department_id' => $dept->id,
            'user_level_id' => $level1->id,
        ]);
        $user->syncRoles(['L1']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/users/' . $user->id . '/update', ['user_level_id' => $level2->id])
            ->assertStatus(200);

        $user->refresh();
        $this->assertTrue($user->hasRole('L2'));
        $this->assertFalse($user->hasRole('L1'));
    }

    public function test_update_does_not_change_role_when_level_is_unchanged(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Dept');
        $level = $this->createLevel('L1', 1);

        Role::firstOrCreate(['name' => 'L1', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'department_id' => $dept->id,
            'user_level_id' => $level->id,
        ]);
        $user->syncRoles(['L1']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/users/' . $user->id . '/update', ['name' => 'Just a name change'])
            ->assertStatus(200);

        $user->refresh();
        $this->assertTrue($user->hasRole('L1'));
    }

    public function test_update_rejects_duplicate_email(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Dept');
        $level = $this->createLevel('L2', 2);

        $userA = User::factory()->create(['email' => 'a@test.com', 'department_id' => $dept->id, 'user_level_id' => $level->id]);
        $userB = User::factory()->create(['email' => 'b@test.com', 'department_id' => $dept->id, 'user_level_id' => $level->id]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/users/' . $userB->id . '/update', ['email' => 'a@test.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_assign_roles_directly_to_user(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();

        Role::firstOrCreate(['name' => 'Compliance', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'L2', 'guard_name' => 'web']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users/' . $user->id . '/roles', [
                'roles' => ['L2', 'Compliance'],
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email]);

        $user->refresh();
        $this->assertTrue($user->hasRole('L2'));
        $this->assertTrue($user->hasRole('Compliance'));
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_a_user(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/users/' . $user->id . '/delete')
            ->assertStatus(200)
            ->assertJson(['message' => 'User deleted successfully.']);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
