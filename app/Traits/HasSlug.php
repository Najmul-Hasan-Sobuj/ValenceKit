<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSlug
{
    /**
     * Boot the trait.
     */
    protected static function bootHasSlug(): void
    {
        static::creating(function (Model $model) {
            $model->generateUniqueSlug();
        });

        static::updating(function (Model $model) {
            $slugField = $model->getSlugField();
            // Optional: Re-generate slug on update if the source field changed and the current slug is empty
            // In most cases, we don't want to change the slug automatically on update to preserve URLs,
            // but if the slug field is intentionally set to empty, we can regenerate it.
            if (empty($model->{$slugField})) {
                $model->generateUniqueSlug();
            }
        });
    }

    /**
     * Generate and assign a unique slug to the model.
     */
    public function generateUniqueSlug(): void
    {
        $slugField = $this->getSlugField();

        // If the slug is already set, don't override it (unless we want strict auto-slugging)
        // Since we are moving away from frontend input, the slug will likely be empty.
        if (! empty($this->{$slugField})) {
            return;
        }

        $sourceString = $this->getSlugSourceString();
        $slug = Str::slug($sourceString);
        $originalSlug = $slug;
        $count = 1;

        // Ensure uniqueness
        while ($this->slugExists($slug)) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $this->{$slugField} = $slug;
    }

    /**
     * Check if a given slug already exists in the database.
     */
    protected function slugExists(string $slug): bool
    {
        $slugField = $this->getSlugField();
        $query = static::where($slugField, $slug);

        // If the model exists, exclude its own ID from the check
        if ($this->exists) {
            $query->where($this->getKeyName(), '!=', $this->getKey());
        }

        // If the model uses a scoping column for uniqueness (e.g., module), apply it here
        // We can check if a method exists on the model to provide additional slug scopes
        if (method_exists($this, 'scopeSlugUniqueness')) {
            $this->scopeSlugUniqueness($query);
        }

        return $query->exists();
    }

    /**
     * Get the string that should be used as the base for the slug.
     */
    protected function getSlugSourceString(): string
    {
        $sourceFields = $this->getSlugSourceFields();

        $sourceStrings = array_map(function ($field) {
            return $this->{$field} ?? '';
        }, (array) $sourceFields);

        $source = implode(' ', array_filter($sourceStrings));

        return empty($source) ? Str::random(8) : $source;
    }

    /**
     * Get the attributes that should be used to generate the slug.
     */
    protected function getSlugSourceFields(): string|array
    {
        return property_exists($this, 'slugSource') ? $this->slugSource : 'name';
    }

    /**
     * Get the database column name for the slug.
     */
    protected function getSlugField(): string
    {
        return property_exists($this, 'slugField') ? $this->slugField : 'slug';
    }
}
