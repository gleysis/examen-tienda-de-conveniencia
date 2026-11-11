<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashCountResource extends JsonResource
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
            'cash_register_session_id' => $this->cash_register_session_id,
            'user_id' => $this->user_id,
            'expected_amount' => $this->expected_amount,
            'counted_amount' => $this->counted_amount,
            'difference' => $this->difference,
            'type' => $this->type,
            'notes' => $this->notes,
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at,
        ];
    }
}
