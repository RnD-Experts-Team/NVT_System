<?php

namespace Tests\Feature\Api;

use Spatie\Permission\Models\Permission;
use Tests\Feature\ApiTestCase;

class PermissionTest extends ApiTestCase
{
    private function makePermission(string $name): Permission
    {
        return Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    // ── Access Control ────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_list_permissions(): void
    {
        $this->getJson('/api/permissions')->assertStatus(401);
    }

    public function test_non_admin_cannot_list_permissions(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/permissions')
            ->assertStatus(403);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_admin_can_list_all_permissions(): void
    {
        $admin = $this->createAdmin();
        $this->makePermission('view_reports');
        $this->makePermission('edit_users');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/permissions')
            ->assertStatus(200)
            ->assertJsonStructure([['id', 'name']]);
    }

    public function test_index_returns_empty_when_no_permissions_exist(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/permissions')
            ->assertStatus(200)
            ->assertJsonCount(0);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_a_permission(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/permissions/create', ['name' => 'manage_reports'])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'manage_reports']);

        $this->assertDatabaseHas('permissions', ['name' => 'manage_reports']);
    }

    public function test_store_requires_name(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/permissions/create', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_duplicate_permission_name(): void
    {
        $admin = $this->createAdmin();
        $this->makePermission('existing_permission');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/permissions/create', ['name' => 'existing_permission'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_admin_can_show_a_permission(): void
    {
        $admin = $this->createAdmin();
        $perm  = $this->makePermission('view_dashboard');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/permissions/' . $perm->id)
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'view_dashboard']);
    }

    public function test_show_returns_404_for_unknown_permission(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/permissions/99999')
            ->assertStatus(404);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_rename_a_permission(): void
    {
        $admin = $this->createAdmin();
        $perm  = $this->makePermission('old_name');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/permissions/' . $perm->id . '/update', ['name' => 'new_name'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'new_name']);

        $this->assertDatabaseHas('permissions', ['id' => $perm->id, 'name' => 'new_name']);
    }

    public function test_update_allows_same_name_on_same_record(): void
    {
        $admin = $this->createAdmin();
        $perm  = $this->makePermission('same_name');

        // Updating with the same name should not trigger a unique violation
        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/permissions/' . $perm->id . '/update', ['name' => 'same_name'])
            ->assertStatus(200);
    }

    public function test_update_rejects_name_used_by_another_permission(): void
    {
        $admin  = $this->createAdmin();
        $this->makePermission('perm_one');
        $perm2  = $this->makePermission('perm_two');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/permissions/' . $perm2->id . '/update', ['name' => 'perm_one'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_a_permission(): void
    {
        $admin = $this->createAdmin();
        $perm  = $this->makePermission('to_delete');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/permissions/' . $perm->id . '/delete')
            ->assertStatus(200)
            ->assertJson(['message' => 'Permission deleted successfully.']);

        $this->assertDatabaseMissing('permissions', ['id' => $perm->id]);
    }
}
