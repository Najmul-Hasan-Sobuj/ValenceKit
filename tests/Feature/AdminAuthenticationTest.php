<?php

use App\Models\Admin;
use App\Models\User;
use App\Notifications\AdminResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('admin logout targets its own route and does not log out the web user', function () {
    $admin = Admin::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')
        ->actingAs($admin, 'admin')
        ->delete(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest('admin');
    $this->assertAuthenticatedAs($user, 'web');
});

test('web user cannot access admin settings or dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')->get(route('admin.profile.edit'))
        ->assertRedirect(route('admin.login'));
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

test('inactive admin cannot access admin dashboard', function () {
    $admin = Admin::factory()->inactive()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
    $this->assertGuest('admin');
});

test('admin passkey options use GET and are isolated from user passkeys', function () {
    $this->getJson(route('admin.passkeys.options'))
        ->assertOk()
        ->assertJsonStructure(['options']);
});

test('admin password reset link points to the admin reset page', function () {
    Notification::fake();
    $admin = Admin::factory()->create();

    $this->post(route('admin.password.email'), ['email' => $admin->email])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($admin, AdminResetPassword::class, function (AdminResetPassword $notification) use ($admin) {
        return str_contains($notification->toMail($admin)->actionUrl, route('admin.password.reset', ['token' => $notification->token]));
    });
});

test('admin and user identities are shared separately', function () {
    $admin = Admin::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user, 'web')->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('auth.admin.id', $admin->id)
            ->where('auth.user.id', $user->id));
});
