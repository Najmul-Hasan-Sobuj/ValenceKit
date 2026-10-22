<?php

namespace App\Http\Resources\Admin\Authorization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            'description' => $this->description,
            'system' => (bool) $this->is_system,
            'members' => $this->members_count ?? $this->whenCounted('users', $this->users_count, 0),
            'permissionKeys' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
        ];
    }
}
