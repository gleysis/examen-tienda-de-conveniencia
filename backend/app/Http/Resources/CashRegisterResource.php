<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashRegisterResource extends JsonResource
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
            'name' => $this->name,
            'location' => $this->location,
            'user_id' => $this->user_id,
            'opening_balance' => $this->opening_balance,
            'current_balance' => $this->current_balance,
            'cash_sales' => $this->cash_sales,
            'card_sales' => $this->card_sales,
            'transfer_sales' => $this->transfer_sales,
            'total_sales' => $this->total_sales,
            'expected_closing' => $this->expected_closing,
            'actual_closing' => $this->actual_closing,
            'difference' => $this->difference,
            'status' => $this->status,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'notes' => $this->notes,
            'user' => new UserResource($this->whenLoaded('user')),
            'sales' => SaleResource::collection($this->whenLoaded('sales')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
