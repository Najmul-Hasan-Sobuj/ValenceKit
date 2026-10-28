<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Webauthn\PublicKeyCredential;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('user passkey login rejects admin passkeys', function () {
    $admin = Admin::factory()->create();
    $adminPasskey = $admin->passkeys()->create([
        'name' => 'Admin Key',
        'credential_id' => 'admin-credential-xyz',
        'credential' => [],
    ]);

    $mockVerify = Mockery::mock(VerifyPasskey::class);
    $mockVerify->shouldReceive('__invoke')
        ->once()
        ->andReturn($adminPasskey);
    $this->app->instance(VerifyPasskey::class, $mockVerify);

    $options = app(GenerateVerificationOptions::class)(null);
    $mockRequest = Mockery::mock(PasskeyVerificationRequest::class);
    $mockRequest->shouldReceive('credential')->andReturn(Mockery::mock(PublicKeyCredential::class));
    $mockRequest->shouldReceive('verificationOptions')->andReturn($options);
    $mockRequest->shouldReceive('remember')->andReturn(false);
    $this->app->instance(PasskeyVerificationRequest::class, $mockRequest);

    $response = $this->postJson(route('passkey.login'));

    $response->assertStatus(422);
    $this->assertGuest('web');
});
