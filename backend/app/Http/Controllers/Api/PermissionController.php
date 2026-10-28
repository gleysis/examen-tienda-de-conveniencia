<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(): array
    {
        return [
            'permissions' => Permission::all(),
        ];
    }

    public function show(Permission $permission): array
    {
        return [
            'permission' => $permission->load('roles'),
        ];
    }

    public function store(Request $request): array
    {
        $request->validate([
            'name' => 'required|string',
            'slug' => 'required|string|unique:permissions,slug',
            'description' => 'nullable|string',
        ]);

        $permission = Permission::create($request->only(['name', 'slug', 'description']));

        return [
            'permission' => $permission,
        ];
    }

    public function update(Request $request, Permission $permission): array
    {
        $request->validate([
            'name' => 'sometimes|required|string',
            'slug' => 'sometimes|required|string|unique:permissions,slug,'.$permission->id,
            'description' => 'nullable|string',
        ]);

        $permission->update($request->only(['name', 'slug', 'description']));

        return [
            'permission' => $permission,
        ];
    }

    public function destroy(Permission $permission): array
    {
        $permission->delete();

        return [
            'message' => 'Permission deleted successfully',
        ];
    }
}
