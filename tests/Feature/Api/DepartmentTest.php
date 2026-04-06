<?php

namespace Tests\Feature\Api;

use Tests\Feature\ApiTestCase;

class DepartmentTest extends ApiTestCase
{
    // ── Access Control ────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_list_departments(): void
    {
        $this->getJson('/api/departments')->assertStatus(401);
    }

    public function test_non_admin_cannot_list_departments(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/departments')
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_create_department(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/departments/create', ['name' => 'Test'])
            ->assertStatus(403);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_admin_can_list_all_departments(): void
    {
        $admin = $this->createAdmin();
        $this->createDepartment('Engineering');
        $this->createDepartment('HR');

        // admin's own HQ dept + Engineering + HR = 3
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/departments')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    // ── Tree ──────────────────────────────────────────────────────────────────

    public function test_tree_returns_only_root_departments_at_top_level(): void
    {
        $dept   = $this->createDepartment('Admin HQ');
        $level  = $this->createLevel('L6', 6);
        $admin  = $this->createAdmin($dept, $level);

        $root  = $this->createDepartment('Root Dept');
        $this->createDepartment('Child Dept', $root);   // child should NOT appear at root level

        // admin's HQ + Root Dept = 2 roots
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/departments/tree')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_tree_nests_children_under_parent(): void
    {
        $admin = $this->createAdmin();
        $root  = $this->createDepartment('Root');
        $this->createDepartment('Child', $root);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/departments/tree')
            ->assertStatus(200);

        // Find Root in the response and assert it has children
        $rootData = collect($response->json('data'))->firstWhere('name', 'Root');
        $this->assertNotNull($rootData);
        $this->assertCount(1, $rootData['children']);
        $this->assertEquals('Child', $rootData['children'][0]['name']);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_root_department(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/departments/create', [
                'name'        => 'Engineering',
                'description' => 'Tech team',
                'is_active'   => true,
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'Engineering']);

        $this->assertDatabaseHas('departments', ['name' => 'Engineering']);
    }

    public function test_root_department_gets_correct_materialized_path(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/departments/create', ['name' => 'Root Dept'])
            ->assertStatus(201);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('departments', ['id' => $id, 'path' => "/{$id}/"]);
    }

    public function test_child_department_inherits_parent_path(): void
    {
        $admin  = $this->createAdmin();
        $parent = $this->createDepartment('Parent');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/departments/create', [
                'name'      => 'Child',
                'parent_id' => $parent->id,
            ])
            ->assertStatus(201);

        $childId = $response->json('data.id');
        $this->assertDatabaseHas('departments', [
            'id'   => $childId,
            'path' => $parent->path . $childId . '/',
        ]);
    }

    public function test_store_requires_name(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/departments/create', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_non_existent_parent_id(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/departments/create', ['name' => 'Test', 'parent_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_admin_can_show_a_department(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Visible Dept');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/departments/' . $dept->id)
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Visible Dept']);
    }

    public function test_show_returns_404_for_unknown_department(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/departments/99999')
            ->assertStatus(404);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_department_name(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Old Name');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/departments/' . $dept->id . '/update', ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('departments', ['id' => $dept->id, 'name' => 'New Name']);
    }

    public function test_admin_can_toggle_department_active_status(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('Active Dept');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/departments/' . $dept->id . '/update', ['is_active' => false])
            ->assertStatus(200)
            ->assertJsonFragment(['is_active' => false]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_empty_department(): void
    {
        $admin = $this->createAdmin();
        $dept  = $this->createDepartment('To Delete');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/departments/' . $dept->id . '/delete')
            ->assertStatus(200)
            ->assertJson(['message' => 'Department deleted successfully.']);

        $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
    }

    public function test_cannot_delete_department_that_has_children(): void
    {
        $admin  = $this->createAdmin();
        $parent = $this->createDepartment('Parent');
        $this->createDepartment('Child', $parent);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/departments/' . $parent->id . '/delete')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete department with child departments.']);
    }

    public function test_cannot_delete_department_that_has_users(): void
    {
        $dept  = $this->createDepartment('Occupied');
        $level = $this->createLevel('L6', 6);
        $admin = $this->createAdmin($dept, $level);

        // Create a second user in the same dept so the dept still has users after admin
        $this->createUser($dept, $level);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/departments/' . $dept->id . '/delete')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete department that has users assigned.']);
    }

    // ── Users in Department Tree ──────────────────────────────────────────────

    public function test_admin_can_get_users_within_department_tree(): void
    {
        $admin     = $this->createAdmin();
        $level     = $this->createLevel('L2', 2);
        $root      = $this->createDepartment('Root');
        $child     = $this->createDepartment('Child', $root);

        $this->createUser($root, $level);
        $this->createUser($child, $level);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/departments/' . $root->id . '/users')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_users_endpoint_only_returns_users_in_subtree(): void
    {
        $admin     = $this->createAdmin();
        $level     = $this->createLevel('L2', 2);
        $root      = $this->createDepartment('Root');
        $unrelated = $this->createDepartment('Unrelated');

        $this->createUser($root, $level);
        $this->createUser($unrelated, $level);  // should NOT appear

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/departments/' . $root->id . '/users')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
