<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    public function store(Request $request): array
    {
        $request->validate([
            'name' => 'required|string',
            'slug' => 'required|string|unique:roles,slug',
            'description' => 'nullable|string',
        ]);

        $role = Role::create($request->only(['name', 'slug', 'description']));

        return [
            'role' => $role,
        ];
    }

    public function update(Request $request, Role $role): array
    {
        $request->validate([
            'name' => 'sometimes|required|string',
            'slug' => 'sometimes|required|string|unique:roles,slug,'.$role->id,
            'description' => 'nullable|string',
        ]);

        $role->update($request->only(['name', 'slug', 'description']));

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
