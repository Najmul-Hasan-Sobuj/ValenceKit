<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property bool $clarity_enabled
 * @property string|null $clarity_project_id
 * @property bool $google_analytics_enabled
 * @property string|null $google_analytics_id
 * @property bool $google_tag_manager_enabled
 * @property string|null $google_tag_manager_id
 * @property bool $facebook_pixel_enabled
 * @property string|null $facebook_pixel_id
 * @property bool $custom_scripts_enabled
 * @property string|null $custom_head_scripts
 * @property string|null $custom_body_scripts
 * @property bool $exclude_admins
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'clarity_enabled',
    'clarity_project_id',
    'google_analytics_enabled',
    'google_analytics_id',
    'google_tag_manager_enabled',
    'google_tag_manager_id',
    'facebook_pixel_enabled',
    'facebook_pixel_id',
    'custom_scripts_enabled',
    'custom_head_scripts',
    'custom_body_scripts',
    'exclude_admins',
])]
class TrackingSetting extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clarity_enabled' => 'boolean',
            'google_analytics_enabled' => 'boolean',
            'google_tag_manager_enabled' => 'boolean',
            'facebook_pixel_enabled' => 'boolean',
            'custom_scripts_enabled' => 'boolean',
            'exclude_admins' => 'boolean',
        ];
    }

    /**
     * Retrieve the singleton tracking settings instance with caching.
     */
    public static function instance(): self
    {
        $attributes = Cache::remember('tracking_settings_instance', 86400, function () {
            $record = static::first();
            if (! $record) {
                $record = static::create([
                    'clarity_enabled' => false,
                    'clarity_project_id' => null,
                    'google_analytics_enabled' => false,
                    'google_analytics_id' => null,
                    'google_tag_manager_enabled' => false,
                    'google_tag_manager_id' => null,
                    'facebook_pixel_enabled' => false,
                    'facebook_pixel_id' => null,
                    'custom_scripts_enabled' => true,
                    'custom_head_scripts' => null,
                    'custom_body_scripts' => null,
                    'exclude_admins' => true,
                ]);
            }

            return $record->getAttributes();
        });

        if (! is_array($attributes)) {
            static::flushCache();
            $record = static::first() ?? static::create([]);

            return $record;
        }

        $instance = new static;
        $instance->setRawAttributes($attributes, true);
        $instance->exists = true;

        return $instance;
    }

    /**
     * Clear the cached tracking settings instance.
     */
    public static function flushCache(): void
    {
        Cache::forget('tracking_settings_instance');
    }
}
