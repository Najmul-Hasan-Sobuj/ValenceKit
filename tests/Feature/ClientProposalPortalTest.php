<?php

use App\Models\Admin;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\ProposalClientInvitation;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('requires login and email verification for every client document endpoint', function () {
    $proposal = Proposal::factory()->create(['status' => 'sent']);
    $this->get(route('client.proposals.index'))->assertRedirect(route('login'));
    $this->get(route('proposals.public.show', $proposal))->assertRedirect(route('login'));
    $this->post(route('proposals.public.send-code', $proposal))->assertRedirect(route('login'));
    $this->post(route('proposals.public.respond', $proposal))->assertRedirect(route('login'));
    $user = User::factory()->unverified()->create(['email' => $proposal->client_email]);
    $this->actingAs($user, 'web');
    $this->get(route('client.proposals.index'))->assertRedirect(route('verification.notice'));
    $this->get(route('proposals.public.show', $proposal))->assertRedirect(route('verification.notice'));
    $this->post(route('proposals.public.send-code', $proposal))->assertRedirect(route('verification.notice'));
    $this->post(route('proposals.public.respond', $proposal))->assertRedirect(route('verification.notice'));
});

it('hides other recipients documents and draft documents from verified clients', function () {
    $user = User::factory()->create();
    $own = Proposal::factory()->create(['status' => 'sent', 'client_email' => strtoupper($user->email)]);
    $other = Proposal::factory()->create(['status' => 'sent']);
    $draft = Proposal::factory()->create(['status' => 'draft', 'client_email' => $user->email]);
    $this->actingAs($user, 'web')->get(route('client.proposals.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('client/Proposals')->has('documents.data', 1)->where('documents.data.0.number', $own->number));
    $this->get(route('proposals.public.show', $own))->assertOk();
    foreach ([$other, $draft] as $document) {
        $this->get(route('proposals.public.show', $document))->assertNotFound();
        $this->post(route('proposals.public.send-code', $document))->assertNotFound();
        $this->post(route('proposals.public.respond', $document), ['decision' => 'accepted', 'signer_name' => 'Intruder', 'verification_code' => '123456', 'fingerprint' => $document->refresh()->approvalFingerprint()])->assertNotFound();
    }
    expect($other->refresh()->status)->toBe('sent');
});

it('lets an invited recipient register verify email and access the document', function () {
    Notification::fake();
    $proposal = Proposal::factory()->create(['status' => 'sent', 'client_email' => 'invited@example.test']);
    $this->get(route('proposals.public.show', $proposal))->assertRedirect(route('login'));
    $this->post(route('register.store'), ['name' => 'Invited Client', 'email' => $proposal->client_email, 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect();
    $user = User::where('email', $proposal->client_email)->firstOrFail();
    Notification::assertSentTo($user, VerifyEmail::class);
    expect($user->hasVerifiedEmail())->toBeFalse();
    $this->get(route('proposals.public.show', $proposal))->assertRedirect(route('verification.notice'));
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->get($url)->assertRedirect();
    $this->get(route('proposals.public.show', $proposal))->assertOk();
    $this->get(route('client.proposals.index'))->assertOk();
});

it('sends invitations only to the saved recipient with admin permission', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();
    $admin = Admin::factory()->create();
    $proposal = Proposal::factory()->create(['status' => 'sent']);
    $this->actingAs($admin, 'admin')->post(route('admin.proposals.invite', $proposal))->assertForbidden();
    $admin->givePermissionTo('proposals.edit');
    $this->post(route('admin.proposals.invite', $proposal), ['email' => 'attacker@example.test'])->assertRedirect()->assertSessionHas('success');
    Notification::assertSentOnDemand(ProposalClientInvitation::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $proposal->client_email && $notification->documentUrl === route('proposals.public.show', $proposal));
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.client_invited', 'subject_id' => $proposal->id, 'actor_id' => $admin->id]);
    $draft = Proposal::factory()->create();
    $this->post(route('admin.proposals.invite', $draft))->assertStatus(409);
});

it('paginates and searches only the signed in clients documents', function () {
    $user = User::factory()->create();
    Proposal::factory()->count(16)->create(['status' => 'sent', 'client_email' => $user->email, 'title' => 'Searchable scope']);
    Proposal::factory()->create(['status' => 'sent', 'title' => 'Searchable scope']);
    $this->actingAs($user, 'web')->get(route('client.proposals.index', ['search' => 'Searchable', 'page' => 2]))->assertOk()->assertInertia(fn ($page) => $page->where('documents.total', 16)->has('documents.data', 1)->where('filters.search', 'Searchable'));
});

it('returns an existing client to the invited document after login', function () {
    $user = User::factory()->create();
    $proposal = Proposal::factory()->create(['status' => 'sent', 'client_email' => $user->email]);
    $this->get(route('proposals.public.show', $proposal))->assertRedirect(route('login'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('proposals.public.show', $proposal));
    $this->get(route('proposals.public.show', $proposal))->assertOk();
    $this->post(route('logout'))->assertRedirect();
    $this->get(route('client.proposals.index'))->assertRedirect(route('login'));
});
