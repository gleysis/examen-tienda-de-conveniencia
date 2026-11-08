<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::with('roles.permissions:id,name,slug,description')
            ->select('id', 'name', 'email', 'created_at', 'updated_at')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): array
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($request->has('roles')) {
            $user->roles()->attach($request->roles);
        }

        return [
            'user' => $user->load('roles', 'roles.permissions'),
            'message' => 'Usuario creado exitosamente',
        ];
    }

    public function show(User $user): array
    {
        return [
            'user' => $user->load('roles', 'roles.permissions'),
        ];
    }

    public function update(UpdateUserRequest $request, User $user): array
    {
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        return [
            'user' => $user->load('roles', 'roles.permissions'),
            'message' => 'Usuario actualizado exitosamente',
        ];
    }

    public function destroy(Request $request, User $user): array
    {
        abort_if($request->user()->is($user), 422, 'No puedes eliminar tu propio usuario.');
        abort_unless($request->user()->holdsAllPermissions($user->permissionSlugs()), 403);

        $user->delete();

        return [
            'message' => 'Usuario eliminado exitosamente',
        ];
    }

    public function roles(): array
    {
        $roles = Role::select('id', 'name', 'slug', 'description')->get();

        return [
            'roles' => $roles,
        ];
    }
}
