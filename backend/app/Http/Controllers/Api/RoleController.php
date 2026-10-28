<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): array
    {
        return [
            'roles' => Role::with('permissions')->get(),
        ];
    }

    public function show(Role $role): array
    {
        return [
            'role' => $role->load('permissions', 'users'),
        ];
    }

    public function store(StoreRoleRequest $request): array
    {
        $role = Role::create($request->validated());

        return [
            'role' => $role,
        ];
    }

    public function update(UpdateRoleRequest $request, Role $role): array
    {
        $role->update($request->validated());

        return [
            'role' => $role,
        ];
    }

    public function destroy(Role $role): array
    {
        $role->delete();

        return [
            'message' => 'Role deleted successfully',
        ];
    }

    public function syncPermissions(Request $request, Role $role): array
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->sync($request->input('permissions'));

        return [
            'role' => $role->load('permissions'),
        ];
    }
}
