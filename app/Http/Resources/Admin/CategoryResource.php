<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'module' => $this->module,
            'type' => $this->type,
            'slug' => $this->slug,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'is_active' => (bool) $this->is_active,
            'is_system' => (bool) $this->is_system,
            'children_count' => (int) ($this->children_count ?? 0),
            'parent' => new CategoryResource($this->whenLoaded('parent')),
        ];
    }
}
