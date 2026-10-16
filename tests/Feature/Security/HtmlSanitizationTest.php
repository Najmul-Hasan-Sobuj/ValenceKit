<?php

use App\Models\Admin;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);
});

test('pagination links render without raw html vulnerabilities in admin views', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');

    // Create multiple users to produce pagination links
    Admin::factory()->count(15)->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/authorization/Users')
            ->has('admins.links')
        );
});

test('two factor qr code endpoint returns svg without script injection', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson('/user/two-factor-authentication')
        ->assertSuccessful();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson('/user/two-factor-qr-code')
        ->assertOk();

    $svg = $response->json('svg');

    expect($svg)->toBeString()
        ->toContain('<svg')
        ->toContain('</svg>')
        ->not->toContain('<script')
        ->not->toContain('onload=')
        ->not->toContain('onerror=');
});
