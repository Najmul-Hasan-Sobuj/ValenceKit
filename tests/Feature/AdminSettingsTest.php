<?php

use App\Models\Admin;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);
});

test('admin with settings permissions can open profile security and appearance settings', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin, 'admin')->get(route('admin.profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/settings/Profile'));
    $this->get(route('admin.security.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/settings/Security'));
    $this->get(route('admin.appearance.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/settings/Appearance'));
});

test('admin updates only their own profile', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');
    $user = User::factory()->create();

    $this->actingAs($admin, 'admin')->patch(route('admin.profile.update'), [
        'name' => 'Updated Admin',
        'email' => 'updated-admin@example.com',
    ])->assertRedirect(route('admin.profile.edit'));

    expect($admin->refresh()->name)->toBe('Updated Admin');
    expect($admin->email_verified_at)->toBeNull();
    expect($user->refresh()->email)->not->toBe('updated-admin@example.com');
});

test('admin must enter their own current password to change it', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin, 'admin')->put(route('admin.security.update'), [
        'current_password' => 'incorrect',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertSessionHasErrors('current_password');
});

test('admin can update their password without changing the web user password', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');
    $user = User::factory()->create();

    $this->actingAs($user, 'web')->actingAs($admin, 'admin')
        ->put(route('admin.security.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertSessionHasNoErrors();

    expect(password_verify('new-secret-password', $admin->refresh()->password))->toBeTrue();
    expect(password_verify('password', $user->refresh()->password))->toBeTrue();
});

test('admin can remove their passkey but not another admins passkey', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');
    $otherAdmin = Admin::factory()->create();
    $otherAdmin->assignRole('Super Admin');
    $ownPasskey = $admin->passkeys()->create([
        'name' => 'My key', 'credential_id' => 'credential-own', 'credential' => [],
    ]);
    $otherPasskey = $otherAdmin->passkeys()->create([
        'name' => 'Other key', 'credential_id' => 'credential-other', 'credential' => [],
    ]);

    $this->actingAs($admin, 'admin')->deleteJson(route('admin.passkeys.destroy', $otherPasskey))
        ->assertNotFound();
    $this->from(route('admin.security.edit'))
        ->delete(route('admin.passkeys.destroy', $ownPasskey))
        ->assertRedirect(route('admin.security.edit'));

    $this->assertModelMissing($ownPasskey);
    $this->assertModelExists($otherPasskey);
});

test('admin cannot register a passkey without requesting options first', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin, 'admin')->postJson(route('admin.passkeys.register'), [
        'name' => 'My key',
        'credential' => ['id' => 'x', 'rawId' => 'x', 'type' => 'public-key', 'response' => []],
    ])->assertUnprocessable();

    expect($admin->passkeys()->count())->toBe(0);
});

test('only admins can register passkeys and options are returned for an authenticated admin', function () {
    $this->getJson(route('admin.passkeys.register.options'))->assertUnauthorized();

    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin, 'admin')->getJson(route('admin.passkeys.register.options'))
        ->assertOk()->assertJsonStructure(['options']);
});

test('unauthorized admin without settings permissions is forbidden from accessing settings tabs', function () {
    $restrictedAdmin = Admin::factory()->create();

    $this->actingAs($restrictedAdmin, 'admin')->get(route('admin.profile.edit'))
        ->assertForbidden();

    $this->actingAs($restrictedAdmin, 'admin')->patch(route('admin.profile.update'), [
        'name' => 'Test',
        'email' => 'test@example.com',
    ])->assertForbidden();

    $this->actingAs($restrictedAdmin, 'admin')->get(route('admin.security.edit'))
        ->assertForbidden();

    $this->actingAs($restrictedAdmin, 'admin')->put(route('admin.security.update'), [
        'current_password' => 'password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertForbidden();

    $this->actingAs($restrictedAdmin, 'admin')->get(route('admin.appearance.edit'))
        ->assertForbidden();
});

test('admin with only profile view permission can access profile tab but not update or access other tabs', function () {
    $profileViewer = Admin::factory()->create();
    $profileViewer->givePermissionTo('settings.profile.view');

    $this->actingAs($profileViewer, 'admin')->get(route('admin.profile.edit'))
        ->assertOk();

    $this->actingAs($profileViewer, 'admin')->patch(route('admin.profile.update'), [
        'name' => 'Should Fail',
        'email' => 'fail@example.com',
    ])->assertForbidden();

    $this->actingAs($profileViewer, 'admin')->get(route('admin.security.edit'))
        ->assertForbidden();

    $this->actingAs($profileViewer, 'admin')->get(route('admin.appearance.edit'))
        ->assertForbidden();
});
