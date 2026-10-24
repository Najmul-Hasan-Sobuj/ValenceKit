<?php

use App\Actions\Admin\VerifyAdminPasskey;
use App\Http\Requests\Admin\AdminPasskeyVerificationRequest;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\AdminResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Webauthn\PublicKeyCredential;

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

test('admin logout invalidates the session and regenerates token', function () {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')
        ->withSession(['custom_admin_key' => 'secret_value'])
        ->delete(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest('admin');
    $response->assertSessionMissing('custom_admin_key');
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

test('authenticated admin visiting login page is redirected to admin dashboard', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.login'))
        ->assertRedirect(route('admin.dashboard'));
});

test('admin passkey authentication records admin.login audit log', function () {
    $admin = Admin::factory()->create();
    $passkey = $admin->passkeys()->create([
        'name' => 'Security Key',
        'credential_id' => 'credential-xyz',
        'credential' => [],
    ]);

    $mockVerify = Mockery::mock(VerifyAdminPasskey::class);
    $mockVerify->shouldReceive('__invoke')
        ->once()
        ->andReturn($passkey);
    $this->app->instance(VerifyAdminPasskey::class, $mockVerify);

    $options = app(GenerateVerificationOptions::class)(null);
    $mockRequest = Mockery::mock(AdminPasskeyVerificationRequest::class);
    $mockRequest->shouldReceive('credential')->andReturn(Mockery::mock(PublicKeyCredential::class));
    $mockRequest->shouldReceive('verificationOptions')->andReturn($options);
    $mockRequest->shouldReceive('remember')->andReturn(false);
    $mockRequest->shouldReceive('ip')->andReturn('127.0.0.1');
    $mockRequest->shouldReceive('session')->andReturn(session()->driver());
    $this->app->instance(AdminPasskeyVerificationRequest::class, $mockRequest);

    $response = $this->postJson(route('admin.passkeys.authenticate'));

    $response->assertOk()
        ->assertJson(['redirect' => route('admin.dashboard')]);

    $this->assertAuthenticatedAs($admin, 'admin');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.login',
        'actor_type' => Admin::class,
        'actor_id' => $admin->id,
        'subject_type' => Admin::class,
        'subject_id' => $admin->id,
        'description' => 'An administrator signed in.',
    ]);
});
