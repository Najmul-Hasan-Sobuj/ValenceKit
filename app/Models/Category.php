<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'module',
    'type',
    'parent_id',
    'slug',
    'name',
    'icon',
    'color',
    'is_active',
    'is_system',
])]
class Category extends Model
{
    use HasFactory, HasSlug;

    /**
     * Scope the slug uniqueness check to the current module.
     */
    protected function scopeSlugUniqueness($query): void
    {
        $query->where('module', $this->module);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (Category $category) {
            if ($category->wasChanged('is_active') && ! $category->is_active) {
                $category->deactivateDescendants();
            }
        });
    }

    /**
     * Recursively deactivate all child categories.
     */
    public function deactivateDescendants(): void
    {
        foreach ($this->children as $child) {
            if ($child->is_active) {
                $child->update(['is_active' => false]);
            }
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include categories for a specific module.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /**
     * Scope a query to only include categories of a specific type.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include active categories.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
