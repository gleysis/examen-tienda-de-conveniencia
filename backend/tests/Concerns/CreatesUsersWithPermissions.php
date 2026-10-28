<?php

namespace Tests\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

trait CreatesUsersWithPermissions
{
    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::factory()->create();

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::factory()->create([
                'slug' => $slug,
                'name' => $slug,
            ]);
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }
}
