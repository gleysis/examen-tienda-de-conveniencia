<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): array
    {
        $users = User::with('roles.permissions:id,name,slug,description')
            ->select('id', 'name', 'email', 'created_at', 'updated_at')
            ->get();

        return [
            'users' => $users,
        ];
    }

    public function store(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ]);

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

    public function update(Request $request, User $user): array
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:6',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ]);

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

    public function destroy(User $user): array
    {
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
