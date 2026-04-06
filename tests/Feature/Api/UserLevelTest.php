<?php

namespace Tests\Feature\Api;

use App\Models\UserLevel;
use Tests\Feature\ApiTestCase;

class UserLevelTest extends ApiTestCase
{
    // ── Access Control ────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_list_levels(): void
    {
        $this->getJson('/api/levels')->assertStatus(401);
    }

    public function test_non_admin_cannot_list_levels(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/levels')
            ->assertStatus(403);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_admin_can_list_all_levels(): void
    {
        $admin = $this->createAdmin(); // creates L6

        UserLevel::firstOrCreate(['code' => 'L1'], ['name' => 'Employee', 'hierarchy_rank' => 1]);
        UserLevel::firstOrCreate(['code' => 'L2'], ['name' => 'Team Lead', 'hierarchy_rank' => 2]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/levels')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'hierarchy_rank', 'tiers']]]);
    }

    public function test_levels_are_ordered_by_hierarchy_rank(): void
    {
        $admin = $this->createAdmin(); // creates L6 (rank 6)
        UserLevel::firstOrCreate(['code' => 'L1'], ['name' => 'Employee', 'hierarchy_rank' => 1]);
        UserLevel::firstOrCreate(['code' => 'L3'], ['name' => 'Manager', 'hierarchy_rank' => 3]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/levels')
            ->assertStatus(200);

        $ranks = array_column($response->json('data'), 'hierarchy_rank');
        $this->assertEquals($ranks, collect($ranks)->sort()->values()->toArray());
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_a_level(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/levels/create', [
                'code'           => 'L7',
                'name'           => 'VP',
                'hierarchy_rank' => 7,
                'description'    => 'Vice President',
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['code' => 'L7', 'name' => 'VP']);

        $this->assertDatabaseHas('user_levels', ['code' => 'L7']);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/levels/create', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'hierarchy_rank']);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $admin = $this->createAdmin(); // creates L6

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/levels/create', [
                'code'           => 'L6',  // already exists
                'name'           => 'Duplicate',
                'hierarchy_rank' => 99,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_store_rejects_non_integer_hierarchy_rank(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/levels/create', [
                'code'           => 'L9',
                'name'           => 'Test',
                'hierarchy_rank' => 'not-a-number',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hierarchy_rank']);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_admin_can_show_a_level(): void
    {
        $admin = $this->createAdmin();
        $level = UserLevel::firstOrCreate(['code' => 'L2'], ['name' => 'Team Lead', 'hierarchy_rank' => 2]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/levels/' . $level->id)
            ->assertStatus(200)
            ->assertJsonFragment(['code' => 'L2'])
            ->assertJsonStructure(['data' => ['tiers']]);
    }

    public function test_show_returns_404_for_unknown_level(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/levels/99999')
            ->assertStatus(404);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_level_name(): void
    {
        $admin = $this->createAdmin();
        $level = UserLevel::firstOrCreate(['code' => 'L2'], ['name' => 'Old Name', 'hierarchy_rank' => 2]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/levels/' . $level->id . '/update', ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('user_levels', ['id' => $level->id, 'name' => 'New Name']);
    }

    public function test_update_rejects_duplicate_code_on_different_level(): void
    {
        $admin  = $this->createAdmin(); // creates L6
        $target = UserLevel::firstOrCreate(['code' => 'L2'], ['name' => 'Team Lead', 'hierarchy_rank' => 2]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/levels/' . $target->id . '/update', ['code' => 'L6'])  // L6 exists on another level
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_level_with_no_users(): void
    {
        $admin = $this->createAdmin();
        $level = UserLevel::firstOrCreate(['code' => 'L2'], ['name' => 'Team Lead', 'hierarchy_rank' => 2]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/levels/' . $level->id . '/delete')
            ->assertStatus(200)
            ->assertJson(['message' => 'Level deleted successfully.']);

        $this->assertDatabaseMissing('user_levels', ['id' => $level->id]);
    }

    public function test_cannot_delete_level_that_has_assigned_users(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Some Dept');
        $level = UserLevel::firstOrCreate(['code' => 'L2'], ['name' => 'Team Lead', 'hierarchy_rank' => 2]);

        $this->createUser($dept, $level);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/levels/' . $level->id . '/delete')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete level that has users assigned.']);
    }
}
