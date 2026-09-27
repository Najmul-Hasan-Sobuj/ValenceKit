<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoriesConfig = config('finance.categories', []);

        foreach (['income', 'expense'] as $type) {
            $items = $categoriesConfig[$type] ?? [];
            foreach ($items as $item) {
                Category::updateOrCreate(
                    [
                        'module' => 'finance',
                        'slug' => $item['id'],
                    ],
                    [
                        'type' => $type,
                        'name' => $item['name'],
                        'icon' => $item['icon'] ?? null,
                        'color' => $item['color'] ?? null,
                        'is_system' => true, // System categories seeded from config
                    ]
                );
            }
        }
    }
}
