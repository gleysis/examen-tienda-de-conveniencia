<?php

namespace App\Http\Resources;

use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingChangeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $definition = SystemSettings::field($this->key);

        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $definition['field']['label'] ?? $this->key,
            'group' => $definition['group'] ?? null,
            'type' => $definition['field']['type'] ?? 'string',
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            'created_at' => $this->created_at,
        ];
    }
}
