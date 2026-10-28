<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Models\Permission;

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

    public function store(StorePermissionRequest $request): array
    {
        $permission = Permission::create($request->validated());

        return [
            'permission' => $permission,
        ];
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): array
    {
        $permission->update($request->validated());

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
