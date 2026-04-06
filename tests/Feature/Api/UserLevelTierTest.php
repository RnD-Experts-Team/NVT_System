<?php

namespace Tests\Feature\Api;

use App\Models\UserLevel;
use App\Models\UserLevelTier;
use Tests\Feature\ApiTestCase;

class UserLevelTierTest extends ApiTestCase
{
    private function tiersUrl(int $levelId): string
    {
        return "/api/levels/{$levelId}/tiers";
    }

    private function tiersCreateUrl(int $levelId): string
    {
        return "/api/levels/{$levelId}/tiers/create";
    }

    private function tierUrl(int $levelId, int $tierId): string
    {
        return "/api/levels/{$levelId}/tiers/{$tierId}";
    }

    private function tierUpdateUrl(int $levelId, int $tierId): string
    {
        return "/api/levels/{$levelId}/tiers/{$tierId}/update";
    }

    private function tierDeleteUrl(int $levelId, int $tierId): string
    {
        return "/api/levels/{$levelId}/tiers/{$tierId}/delete";
    }

    // ── Access Control ────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_tiers(): void
    {
        $level = $this->createLevel('L2', 2);

        $this->getJson($this->tiersUrl($level->id))->assertStatus(401);
    }

    public function test_non_admin_cannot_access_tiers(): void
    {
        $user  = $this->createUser();
        $level = $this->createLevel('L2', 2);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->tiersUrl($level->id))
            ->assertStatus(403);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_admin_can_list_tiers_for_a_level(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createLevel('L2', 2);

        $level->tiers()->createMany([
            ['tier_name' => 'Tier 1', 'tier_order' => 1],
            ['tier_name' => 'Tier 2', 'tier_order' => 2],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson($this->tiersUrl($level->id))
            ->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_index_returns_empty_array_for_level_with_no_tiers(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createLevel('L2', 2);

        $this->actingAs($admin, 'sanctum')
            ->getJson($this->tiersUrl($level->id))
            ->assertStatus(200)
            ->assertJsonCount(0);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_tier_under_a_level(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createLevel('L2', 2);

        $this->actingAs($admin, 'sanctum')
            ->postJson($this->tiersCreateUrl($level->id), [
                'tier_name'   => 'Tier 1',
                'tier_order'  => 1,
                'description' => 'Entry tier',
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['tier_name' => 'Tier 1']);

        $this->assertDatabaseHas('user_level_tiers', [
            'user_level_id' => $level->id,
            'tier_name'     => 'Tier 1',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createLevel('L2', 2);

        $this->actingAs($admin, 'sanctum')
            ->postJson($this->tiersCreateUrl($level->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tier_name', 'tier_order']);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_admin_can_show_a_tier(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createLevel('L2', 2);
        $tier  = $level->tiers()->create(['tier_name' => 'Tier 1', 'tier_order' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->getJson($this->tierUrl($level->id, $tier->id))
            ->assertStatus(200)
            ->assertJsonFragment(['tier_name' => 'Tier 1']);
    }

    public function test_show_returns_404_when_tier_belongs_to_different_level(): void
    {
        $admin  = $this->createAdmin();
        $level1 = $this->createLevel('L1', 1);
        $level2 = $this->createLevel('L2', 2);
        $tier   = $level2->tiers()->create(['tier_name' => 'Tier 1', 'tier_order' => 1]);

        // Tier belongs to level2 but URL uses level1 — should 404
        $this->actingAs($admin, 'sanctum')
            ->getJson($this->tierUrl($level1->id, $tier->id))
            ->assertStatus(404);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_a_tier(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createLevel('L2', 2);
        $tier  = $level->tiers()->create(['tier_name' => 'Old Name', 'tier_order' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->putJson($this->tierUpdateUrl($level->id, $tier->id), [
                'tier_name' => 'New Name',
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['tier_name' => 'New Name']);

        $this->assertDatabaseHas('user_level_tiers', ['id' => $tier->id, 'tier_name' => 'New Name']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_tier_with_no_users(): void
    {
        $admin = $this->createAdmin();
        $level = $this->createLevel('L2', 2);
        $tier  = $level->tiers()->create(['tier_name' => 'To Delete', 'tier_order' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson($this->tierDeleteUrl($level->id, $tier->id))
            ->assertStatus(200)
            ->assertJson(['message' => 'Tier deleted successfully.']);

        $this->assertDatabaseMissing('user_level_tiers', ['id' => $tier->id]);
    }

    public function test_cannot_delete_tier_that_has_assigned_users(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Dept');
        $level = $this->createLevel('L2', 2);
        $tier  = $level->tiers()->create(['tier_name' => 'In Use', 'tier_order' => 1]);

        // Assign a user to this tier
        \App\Models\User::factory()->create([
            'department_id'      => $dept->id,
            'user_level_id'      => $level->id,
            'user_level_tier_id' => $tier->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson($this->tierDeleteUrl($level->id, $tier->id))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete tier that has users assigned.']);
    }
}
