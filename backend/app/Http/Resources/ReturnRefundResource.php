<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRefundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'sale_id' => $this->sale_id,
            'sale_item_id' => $this->sale_item_id,
            'product_id' => $this->product_id,
            'user_id' => $this->user_id,
            'quantity' => $this->quantity,
            'refund_amount' => $this->refund_amount,
            'reason' => $this->reason,
            'reason_description' => $this->reason_description,
            'status' => $this->status,
            'refund_method' => $this->refund_method,
            'reference_number' => $this->reference_number,
            'processed_date' => $this->processed_date,
            'notes' => $this->notes,
            'sale' => new SaleResource($this->whenLoaded('sale')),
            'sale_item' => new SaleItemResource($this->whenLoaded('saleItem')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
