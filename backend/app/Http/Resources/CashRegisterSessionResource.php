<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashRegisterSessionResource extends JsonResource
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
            'cash_register_id' => $this->cash_register_id,
            'user_id' => $this->user_id,
            'opened_by' => $this->opened_by,
            'closed_by' => $this->closed_by,
            'opening_balance' => $this->opening_balance,
            'cash_sales' => $this->cash_sales,
            'card_sales' => $this->card_sales,
            'transfer_sales' => $this->transfer_sales,
            'total_sales' => $this->total_sales,
            'expected_closing' => $this->expected_closing,
            'status' => $this->status,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'notes' => $this->notes,
            'cash_register' => new CashRegisterResource($this->whenLoaded('cashRegister')),
            'user' => new UserResource($this->whenLoaded('user')),
            'opened_by_user' => new UserResource($this->whenLoaded('openedBy')),
            'closed_by_user' => new UserResource($this->whenLoaded('closedBy')),
            'cash_count' => new CashCountResource($this->whenLoaded('cashCount')),
            'sales' => SaleResource::collection($this->whenLoaded('sales')),
            'movements' => $this->whenLoaded('movements'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
