<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        if (! $actor->holdsAllPermissions($target->permissionSlugs())) {
            return false;
        }

        if (! $this->has('roles')) {
            return true;
        }

        $requestedRoleIds = collect((array) $this->input('roles'))->map(fn ($id) => (int) $id)->sort()->values();
        $rolesChanged = $requestedRoleIds->all() !== $target->roles->pluck('id')->sort()->values()->all();

        return ! ($rolesChanged && $actor->is($target))
            && $actor->holdsAllPermissions(Role::permissionSlugsFor($this->input('roles')));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => ['sometimes', 'string', Password::defaults()],
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ];
    }
}
