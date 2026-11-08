<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $role = $this->route('role');
        $changesSystemSlug = $role->isSystemRole() && $this->has('slug') && $this->input('slug') !== $role->slug;

        return ! $changesSystemSlug
            && $this->user()->holdsAllPermissions($role->permissions()->pluck('slug'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string',
            'slug' => ['sometimes', 'required', 'string', Rule::unique('roles', 'slug')->ignore($this->route('role'))],
            'description' => 'nullable|string',
        ];
    }
}
