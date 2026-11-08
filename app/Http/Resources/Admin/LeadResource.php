<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->only(['id', 'source', 'source_id', 'name', 'phone', 'email', 'address', 'website', 'maps_url', 'category', 'rating', 'review_count', 'query', 'details', 'created_at']);
    }
}
