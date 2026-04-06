<?php

namespace Tests\Feature\Api;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\ApiTestCase;

class RoleTest extends ApiTestCase
{
    private function makeRole(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    private function makePermission(string $name): Permission
    {
        return Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    // ── Access Control ────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_list_roles(): void
    {
        $this->getJson('/api/roles')->assertStatus(401);
    }

    public function test_non_admin_cannot_list_roles(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/roles')
            ->assertStatus(403);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_admin_can_list_all_roles(): void
    {
        $admin = $this->createAdmin();
        $this->makeRole('editor');
        $this->makeRole('viewer');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/roles')
            ->assertStatus(200)
            ->assertJsonStructure([['id', 'name', 'permissions']]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_a_role(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/roles/create', ['name' => 'new_role'])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'new_role']);

        $this->assertDatabaseHas('roles', ['name' => 'new_role']);
    }

    public function test_store_requires_name(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/roles/create', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_duplicate_role_name(): void
    {
        $admin = $this->createAdmin();
        $this->makeRole('existing_role');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/roles/create', ['name' => 'existing_role'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_admin_can_show_a_role(): void
    {
        $admin = $this->createAdmin();
        $role  = $this->makeRole('my_role');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/roles/' . $role->id)
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'my_role'])
            ->assertJsonStructure(['name', 'permissions']);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_rename_a_role(): void
    {
        $admin = $this->createAdmin();
        $role  = $this->makeRole('old_role');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/roles/' . $role->id . '/update', ['name' => 'renamed_role'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'renamed_role']);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'renamed_role']);
    }

    public function test_update_rejects_name_used_by_another_role(): void
    {
        $admin  = $this->createAdmin();
        $role1  = $this->makeRole('role_one');
        $role2  = $this->makeRole('role_two');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/roles/' . $role2->id . '/update', ['name' => 'role_one'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_a_role(): void
    {
        $admin = $this->createAdmin();
        $role  = $this->makeRole('deletable_role');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/roles/' . $role->id . '/delete')
            ->assertStatus(200)
            ->assertJson(['message' => 'Role deleted successfully.']);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    // ── Assign Permissions ────────────────────────────────────────────────────

    public function test_admin_can_assign_permissions_to_a_role(): void
    {
        $admin = $this->createAdmin();
        $role  = $this->makeRole('editor');
        $perm1 = $this->makePermission('edit_posts');
        $perm2 = $this->makePermission('delete_posts');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/roles/' . $role->id . '/permissions', [
                'permissions' => ['edit_posts', 'delete_posts'],
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['message' => 'Permissions assigned successfully.'])
            ->assertJsonFragment(['role' => 'editor']);

        $this->assertTrue($role->fresh()->hasPermissionTo('edit_posts'));
        $this->assertTrue($role->fresh()->hasPermissionTo('delete_posts'));
    }

    public function test_assign_permissions_syncs_and_removes_old_ones(): void
    {
        $admin = $this->createAdmin();
        $role  = $this->makeRole('editor');
        $this->makePermission('old_perm');
        $this->makePermission('new_perm');

        $role->syncPermissions(['old_perm']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/roles/' . $role->id . '/permissions', [
                'permissions' => ['new_perm'],
            ])
            ->assertStatus(200);

        $this->assertTrue($role->fresh()->hasPermissionTo('new_perm'));
        $this->assertFalse($role->fresh()->hasPermissionTo('old_perm'));
    }

    public function test_assign_permissions_validates_permissions_exist(): void
    {
        $admin = $this->createAdmin();
        $role  = $this->makeRole('editor');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/roles/' . $role->id . '/permissions', [
                'permissions' => ['non_existent_permission'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_assign_permissions_requires_permissions_array(): void
    {
        $admin = $this->createAdmin();
        $role  = $this->makeRole('editor');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/roles/' . $role->id . '/permissions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['permissions']);
    }
}
