<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'enabled'])]
class Module extends Model
{
    /**
     * @return array<int, array{key: string, name: string, description: string, enabled: bool}>
     */
    public static function configured(): array
    {
        $states = static::query()->pluck('enabled', 'key');

        return collect(config('modules', []))
            ->map(fn (array $module, string $key): array => [
                'key' => $key,
                'name' => $module['name'],
                'description' => $module['description'],
                'enabled' => (bool) $states->get($key, true),
            ])
            ->values()
            ->all();
    }

    public static function enabled(string $key): bool
    {
        if (! array_key_exists($key, config('modules', []))) {
            return false;
        }

        $enabled = static::query()->where('key', $key)->value('enabled');

        return $enabled === null ? true : (bool) $enabled;
    }

    /** @return array<string, bool> */
    public static function states(): array
    {
        return collect(static::configured())
            ->mapWithKeys(fn (array $module): array => [$module['key'] => $module['enabled']])
            ->all();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
