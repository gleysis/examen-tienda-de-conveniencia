<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->holdsAllPermissions([$this->route('permission')->slug]);
    }

    /**
     * The slug is immutable: authorization checks reference it in code, so renaming
     * a permission could silently grant or revoke access.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string',
            'description' => 'nullable|string',
        ];
    }
}
