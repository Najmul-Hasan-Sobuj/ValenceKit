<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['collection', 'uploaded_by', 'name', 'alt', 'folder', 'disk', 'path', 'mime_type', 'size', 'width', 'height'])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    /** @return MorphTo< Model, $this> */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['name', 'alt', 'folder', 'collection', 'mediable_type', 'mediable_id', 'mime_type', 'size']);
    }
}
