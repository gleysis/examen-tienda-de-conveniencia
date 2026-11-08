<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    private const USER_MANAGER_PERMISSIONS = ['users.view', 'users.create', 'users.edit', 'users.delete'];

    private const ADMIN_PERMISSIONS = [...self::USER_MANAGER_PERMISSIONS, 'backups.manage', 'settings.edit'];

    private function roleWith(array $permissionSlugs, ?string $slug = null): Role
    {
        $role = Role::factory()->create($slug ? ['slug' => $slug] : []);
        $role->permissions()->attach(
            collect($permissionSlugs)->map(fn (string $permissionSlug) => Permission::firstOrCreate(
                ['slug' => $permissionSlug],
                ['name' => $permissionSlug],
            )->id)
        );

        return $role;
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    public function test_cannot_assign_self_a_more_privileged_role(): void
    {
        $adminRole = $this->roleWith(self::ADMIN_PERMISSIONS, 'admin');
        $actor = $this->userWithRole($this->roleWith(self::USER_MANAGER_PERMISSIONS));
        Sanctum::actingAs($actor);

        $this->putJson("/api/users/{$actor->id}", ['roles' => [$adminRole->id]])->assertForbidden();

        $this->assertFalse($actor->fresh()->hasRole('admin'));
    }

    public function test_cannot_create_user_with_more_privileged_role(): void
    {
        $adminRole = $this->roleWith(self::ADMIN_PERMISSIONS, 'admin');
        Sanctum::actingAs($this->userWithRole($this->roleWith(self::USER_MANAGER_PERMISSIONS)));

        $this->postJson('/api/users', [
            'name' => 'Cómplice',
            'email' => 'complice@example.com',
            'password' => 'secret123',
            'roles' => [$adminRole->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'complice@example.com']);
    }

    public function test_cannot_change_password_of_more_privileged_user(): void
    {
        $admin = $this->userWithRole($this->roleWith(self::ADMIN_PERMISSIONS, 'admin'));
        $originalPassword = $admin->password;
        Sanctum::actingAs($this->userWithRole($this->roleWith(self::USER_MANAGER_PERMISSIONS)));

        $this->putJson("/api/users/{$admin->id}", ['password' => 'hacked123'])->assertForbidden();
        $this->deleteJson("/api/users/{$admin->id}")->assertForbidden();

        $this->assertSame($originalPassword, $admin->fresh()->password);
    }

    public function test_cannot_grant_permissions_the_actor_does_not_hold(): void
    {
        $actorRole = $this->roleWith(self::USER_MANAGER_PERMISSIONS);
        $backupsPermission = Permission::firstOrCreate(['slug' => 'backups.manage'], ['name' => 'backups.manage']);
        Sanctum::actingAs($this->userWithRole($actorRole));

        $this->postJson("/api/roles-list/{$actorRole->id}/sync-permissions", [
            'permissions' => [...$actorRole->permissions->pluck('id'), $backupsPermission->id],
        ])->assertForbidden();

        $this->assertFalse($actorRole->fresh()->hasPermission('backups.manage'));
    }

    public function test_cannot_rename_permission_slug(): void
    {
        $actor = $this->userWithRole($this->roleWith(self::USER_MANAGER_PERMISSIONS));
        $permission = Permission::where('slug', 'users.view')->first();
        Sanctum::actingAs($actor);

        $this->putJson("/api/permissions/{$permission->id}", ['name' => 'Ver', 'slug' => 'backups.manage'])->assertOk();

        $this->assertSame('users.view', $permission->fresh()->slug);
    }

    public function test_cannot_rename_or_delete_system_role(): void
    {
        $cashierRole = $this->roleWith(['sales.create'], 'cashier');
        Sanctum::actingAs($this->userWithRole($this->roleWith([...self::ADMIN_PERMISSIONS, 'sales.create'])));

        $this->putJson("/api/roles-list/{$cashierRole->id}", ['slug' => 'admin-2'])->assertForbidden();
        $this->deleteJson("/api/roles-list/{$cashierRole->id}")->assertUnprocessable();

        $this->assertSame('cashier', $cashierRole->fresh()->slug);
    }

    public function test_admin_can_still_manage_users_and_roles(): void
    {
        $adminRole = $this->roleWith([...self::ADMIN_PERMISSIONS, 'sales.create'], 'admin');
        $cashierRole = $this->roleWith(['sales.create'], 'cashier');
        $admin = $this->userWithRole($adminRole);
        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'Cajera',
            'email' => 'cajera@example.com',
            'password' => 'secret123',
            'roles' => [$cashierRole->id],
        ])->assertOk();

        $this->putJson("/api/users/{$admin->id}", ['name' => 'Admin', 'roles' => [$adminRole->id]])->assertOk();
        $this->postJson("/api/roles-list/{$cashierRole->id}/sync-permissions", [
            'permissions' => Permission::whereIn('slug', ['sales.create', 'users.view'])->pluck('id'),
        ])->assertOk();
    }

    public function test_cannot_delete_own_user(): void
    {
        $admin = $this->userWithRole($this->roleWith(self::ADMIN_PERMISSIONS, 'admin'));
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$admin->id}")->assertUnprocessable();

        $this->assertModelExists($admin);
    }
}
