<?php

use App\Models\Admin;
use App\Models\Category;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->assignRole('Super Admin');
});

it('can render categories index page with correct props', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/categories/Index')
            ->has('categories.data')
            ->has('parentCategories')
            ->has('filters')
        );
});

it('can create a category with null type (general category)', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->post(route('admin.categories.store'), [
            'module' => 'product',
            'type' => null,
            'name' => 'Physical Goods',
            'slug' => 'physical_goods',
            'icon' => 'Package',
            'color' => '#10b981',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'module' => 'product',
        'type' => null,
        'slug' => 'physical-goods',
        'name' => 'Physical Goods',
    ]);
});

it('can auto-generate a unique slug if left blank', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->post(route('admin.categories.store'), [
            'module' => 'finance',
            'type' => 'income',
            'name' => 'General Income',
            'slug' => '',
            'icon' => 'Coins',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'name' => 'General Income',
        'slug' => 'general-income',
    ]);

    // Create another one with the same name to test uniqueness
    $this->actingAs($this->superAdmin, 'admin')
        ->post(route('admin.categories.store'), [
            'module' => 'finance',
            'type' => 'income',
            'name' => 'General Income',
            'slug' => '',
            'icon' => 'Coins',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'name' => 'General Income',
        'slug' => 'general-income-1',
    ]);
});

it('can create a finance expense category with a parent category', function () {
    $parent = Category::create([
        'module' => 'finance',
        'type' => 'expense',
        'name' => 'Infrastructure',
        'slug' => 'infra_parent',
        'icon' => 'Server',
        'color' => '#6366f1',
        'is_system' => false,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->post(route('admin.categories.store'), [
            'module' => 'finance',
            'type' => 'expense',
            'parent_id' => $parent->id,
            'name' => 'Database Servers',
            'slug' => 'db_servers',
            'icon' => 'Database',
            'color' => '#6366f1',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'module' => 'finance',
        'type' => 'expense',
        'parent_id' => $parent->id,
        'slug' => 'database-servers',
    ]);
});

it('always displays categories ordered by latest created first', function () {
    $first = Category::create([
        'module' => 'finance',
        'type' => 'expense',
        'name' => 'Oldest Category',
        'slug' => 'oldest_cat',
        'is_system' => false,
    ]);
    $first->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();

    $second = Category::create([
        'module' => 'finance',
        'type' => 'expense',
        'name' => 'Newest Category',
        'slug' => 'newest_cat',
        'is_system' => false,
    ]);
    $second->forceFill(['created_at' => now()])->saveQuietly();

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/categories/Index')
            ->where('categories.data.0.id', $second->id)
            ->where('categories.data.1.id', $first->id)
        );
});

it('can update category details', function () {
    $category = Category::create([
        'module' => 'finance',
        'type' => 'income',
        'name' => 'Sponsorships',
        'slug' => 'sponsorships',
        'icon' => 'Heart',
        'color' => '#ec4899',
        'is_system' => false,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->patch(route('admin.categories.update', $category), [
            'module' => 'finance',
            'type' => 'income',
            'name' => 'Corporate Sponsorships',
            'slug' => 'sponsorships',
            'icon' => 'Award',
            'color' => '#f43f5e',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Corporate Sponsorships',
        'icon' => 'Award',
    ]);
});

it('cannot delete system category', function () {
    $systemCategory = Category::create([
        'module' => 'finance',
        'type' => 'income',
        'name' => 'System Income',
        'slug' => 'system_income',
        'is_system' => true,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->delete(route('admin.categories.destroy', $systemCategory))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('categories', [
        'id' => $systemCategory->id,
    ]);
});

it('can delete custom non-system category', function () {
    $customCategory = Category::create([
        'module' => 'finance',
        'type' => 'income',
        'name' => 'Custom Category',
        'slug' => 'custom_cat',
        'is_system' => false,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->delete(route('admin.categories.destroy', $customCategory))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('categories', [
        'id' => $customCategory->id,
    ]);
});

it('automatically deactivates children when parent category is turned inactive', function () {
    $parent = Category::create([
        'module' => 'finance',
        'type' => 'expense',
        'name' => 'Server Parent',
        'slug' => 'server_parent',
        'is_active' => true,
        'is_system' => false,
    ]);

    $child1 = Category::create([
        'module' => 'finance',
        'type' => 'expense',
        'parent_id' => $parent->id,
        'name' => 'Sub Server 1',
        'slug' => 'sub_server_1',
        'is_active' => true,
        'is_system' => false,
    ]);

    $child2 = Category::create([
        'module' => 'finance',
        'type' => 'expense',
        'parent_id' => $parent->id,
        'name' => 'Sub Server 2',
        'slug' => 'sub_server_2',
        'is_active' => true,
        'is_system' => false,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->patch(route('admin.categories.update', $parent), [
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($parent->fresh()->is_active)->toBeFalse()
        ->and($child1->fresh()->is_active)->toBeFalse()
        ->and($child2->fresh()->is_active)->toBeFalse();
});
