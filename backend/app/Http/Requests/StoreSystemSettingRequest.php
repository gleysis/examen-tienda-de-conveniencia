<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSystemSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => 'required|string|max:255|unique:system_settings',
            'value' => 'nullable|string',
            'type' => 'required|in:string,integer,float,boolean,array,json',
            'group' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ];
    }
}
