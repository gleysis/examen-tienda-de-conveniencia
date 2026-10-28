<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRefundRequest extends FormRequest
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
            'sale_id' => 'required|exists:sales,id',
            'sale_item_id' => 'required|exists:sale_items,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'refund_amount' => 'required|numeric|min:0',
            'reason' => 'required|in:defective,wrong_item,no_longer_needed,customer_preference,other',
            'reason_description' => 'nullable|string',
            'refund_method' => 'required|in:cash,card,transfer,store_credit',
            'notes' => 'nullable|string',
        ];
    }
}
