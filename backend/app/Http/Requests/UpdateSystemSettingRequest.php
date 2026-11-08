<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingRequest extends FormRequest
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
            'value' => 'nullable|string',
            'type' => 'sometimes|in:string,text,integer,float,boolean,array,json',
            'group' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
        ];
    }
}
