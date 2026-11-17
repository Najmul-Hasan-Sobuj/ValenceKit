<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed>|null $details
 */
#[Fillable(['source', 'source_id', 'name', 'phone', 'email', 'address', 'website', 'maps_url', 'category', 'rating', 'review_count', 'query', 'details', 'assigned_to', 'stage', 'next_follow_up_at', 'follow_up_notified_at'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['details' => 'array', 'next_follow_up_at' => 'datetime', 'follow_up_notified_at' => 'datetime', 'rating' => 'float', 'review_count' => 'integer'];
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['name', 'source', 'source_id', 'assigned_to', 'stage', 'next_follow_up_at']);
    }
}
