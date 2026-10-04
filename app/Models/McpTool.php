<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $class_name
 * @property string|null $description
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'slug',
    'class_name',
    'description',
    'version',
    'provider',
    'transport',
    'required_scope',
    'health_status',
    'last_synced_at',
    'is_active',
    'settings',
])]
class McpTool extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }
}
