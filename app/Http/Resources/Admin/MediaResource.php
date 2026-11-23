<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'alt' => $this->alt, 'folder' => $this->folder,
            'collection' => $this->collection, 'mime_type' => $this->mime_type, 'size' => $this->size,
            'width' => $this->width, 'height' => $this->height, 'created_at' => $this->created_at->toIso8601String(),
            'url' => route('admin.media.file', ['media' => $this->id, 'v' => substr(hash('sha256', $this->path), 0, 12)]),
            'owner' => $this->whenLoaded('mediable', fn () => $this->mediable?->name),
            'can_edit' => $request->user('admin')->can('media.edit') && (! $this->mediable_type || $request->user('admin')->can('employees.edit')),
            'can_delete' => $request->user('admin')->can('media.delete') && (! $this->mediable_type || $request->user('admin')->can('employees.edit')),
        ];
    }
}
