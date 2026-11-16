<?php

use App\Models\Admin;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\ProposalClientInvitation;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('provisions an unverified client with a private single-use password setup link', function () {
    Notification::fake();
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.edit');
    $proposal = Proposal::factory()->create(['status' => 'sent', 'client_email' => 'new-client@example.test']);
    $this->actingAs($admin, 'admin')->post(route('admin.proposals.invite', $proposal))->assertSessionHas('success');
    $user = User::where('email', $proposal->client_email)->firstOrFail();
    expect($user->email_verified_at)->toBeNull();
    expect(Hash::check('password', $user->password))->toBeFalse();
    $token = '';
    Notification::assertSentOnDemand(ProposalClientInvitation::class, function ($notification) use (&$token, $user) {
        $parts = parse_url($notification->setupUrl);
        $token = basename($parts['path']);
        parse_str($parts['query'], $query);

        return $query['email'] === $user->email && Password::broker('users')->tokenExists($user, $token);
    });
    $this->post(route('admin.proposals.invite', $proposal))->assertSessionHas('error', 'Please wait one minute before requesting another setup link.');
    $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'Client-chosen-passphrase-487!', 'password_confirmation' => 'Client-chosen-passphrase-487!'])->assertSessionHasNoErrors()->assertRedirect(route('login'));
    expect(Hash::check('Client-chosen-passphrase-487!', $user->refresh()->password))->toBeTrue();
    expect($user->hasVerifiedEmail())->toBeFalse();
    $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'Another-passphrase-487!', 'password_confirmation' => 'Another-passphrase-487!'])->assertSessionHasErrors('email');
    $this->actingAs($user, 'web')->get(route('proposals.public.show', $proposal))->assertRedirect(route('verification.notice'));
});

it('requires a dedicated permission and sends reset links to the bound account current email', function () {
    Notification::fake();
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.view');
    $user = User::factory()->create(['email' => 'current@example.test']);
    $originalPassword = $user->password;
    $proposal = Proposal::factory()->create(['client_email' => 'old@example.test']);
    $proposal->forceFill(['client_user_id' => $user->id, 'owner_claimed_at' => now()])->save();
    $this->actingAs($admin, 'admin')->post(route('admin.proposals.password-reset', $proposal))->assertForbidden();
    $admin->givePermissionTo('clients.password-reset');
    $this->post(route('admin.proposals.password-reset', $proposal), ['email' => 'attacker@example.test', 'password' => 'ignored-value'])->assertSessionHas('success');
    expect($user->refresh()->password)->toBe($originalPassword);
    Notification::assertSentOnDemand(ResetPassword::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $user->email);
    $this->post(route('admin.proposals.password-reset', $proposal))->assertSessionHas('error', 'Please wait one minute before requesting another reset link.');
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.password_reset_delivery', 'subject_id' => $proposal->id]);
});

it('preserves an existing verified account password when inviting it', function () {
    Notification::fake();
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.edit');
    $user = User::factory()->create();
    $password = $user->password;
    $proposal = Proposal::factory()->create(['status' => 'sent', 'client_email' => $user->email]);
    $this->actingAs($admin, 'admin')->post(route('admin.proposals.invite', $proposal))->assertSessionHas('success');
    expect($user->refresh()->password)->toBe($password);
    Notification::assertSentOnDemand(ProposalClientInvitation::class, fn ($notification) => $notification->setupUrl === null);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

it('allows retry after failed setup delivery without leaving a usable token', function () {
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP failed'));
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.edit');
    $proposal = Proposal::factory()->create(['status' => 'sent']);
    $this->actingAs($admin, 'admin')->post(route('admin.proposals.invite', $proposal))->assertSessionHas('error');
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $proposal->client_email]);
    expect($proposal->refresh()->invitation_status)->toBe('failed');
    Notification::fake();
    $this->post(route('admin.proposals.invite', $proposal))->assertSessionHas('success');
    Notification::assertSentOnDemand(ProposalClientInvitation::class, fn ($notification) => $notification->setupUrl !== null);
});

it('rejects an expired client setup link', function () {
    Notification::fake();
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.edit');
    $proposal = Proposal::factory()->create(['status' => 'sent']);
    $this->actingAs($admin, 'admin')->post(route('admin.proposals.invite', $proposal))->assertSessionHas('success');
    $token = '';
    Notification::assertSentOnDemand(ProposalClientInvitation::class, function ($notification) use (&$token) {
        $token = basename(parse_url($notification->setupUrl, PHP_URL_PATH));

        return true;
    });
    $this->travel(61)->minutes();
    $this->post(route('password.update'), ['token' => $token, 'email' => $proposal->client_email, 'password' => 'Client-chosen-passphrase-487!', 'password_confirmation' => 'Client-chosen-passphrase-487!'])->assertSessionHasErrors('email');
});
