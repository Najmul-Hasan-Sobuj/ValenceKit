<?php

use App\Models\Admin;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\ProposalVerificationCode;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->travelTo('2026-11-16 12:00:00');
});

it('loads the drawer preview only for authorized admins without exposing verification secrets or restricted invoices', function () {
    $proposal = Proposal::factory()->create(['status' => 'sent']);
    $proposal->forceFill(['recipient_verification' => ['hash' => 'private-verification-hash', 'expires_at' => now()->addMinutes(10)->timestamp, 'attempts' => 0, 'fingerprint' => $proposal->approvalFingerprint()]])->save();
    Invoice::create(['proposal_id' => $proposal->id, 'number' => 'QA-DRAWER-INVOICE', 'customer_name' => 'Client', 'description' => 'Scope', 'amount_minor' => 100, 'currency' => 'BDT', 'issued_on' => '2026-11-16', 'due_on' => '2026-11-30', 'status' => 'draft']);
    $this->getJson(route('admin.proposals.preview', $proposal))->assertUnauthorized();
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->getJson(route('admin.proposals.preview', $proposal))->assertForbidden();
    $admin->givePermissionTo('proposals.view');
    $this->getJson(route('admin.proposals.preview', $proposal))->assertOk()
        ->assertJsonPath('preview', true)
        ->assertJsonPath('proposal.public_id', $proposal->public_id)
        ->assertJsonPath('proposal.invoice', null)
        ->assertJsonMissingPath('proposal.recipient_verification');
    $admin->givePermissionTo('invoices.view');
    $this->getJson(route('admin.proposals.preview', $proposal))->assertOk()->assertJsonPath('proposal.invoice.number', 'QA-DRAWER-INVOICE');
});

function workflowCode(Proposal $proposal): string
{
    Notification::fake();
    test()->post(route('proposals.public.send-code', $proposal), ['fingerprint' => $proposal->refresh()->approvalFingerprint()])->assertSessionHasNoErrors();
    $code = '';
    Notification::assertSentOnDemand(ProposalVerificationCode::class, function ($notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    return $code;
}

it('rejects a stale browser revision before issuing an approval code', function () {
    Notification::fake();
    $user = User::factory()->create();
    $proposal = Proposal::factory()->create(['status' => 'sent', 'client_email' => $user->email]);
    $fingerprint = $proposal->refresh()->approvalFingerprint();
    $proposal->update(['title' => 'Updated scope']);
    $this->actingAs($user)->post(route('proposals.public.send-code', $proposal), ['fingerprint' => $fingerprint])->assertSessionHasErrors('verification_code');
    Notification::assertNothingSent();
});

it('keeps ownership after an email change and prevents email-based account reclamation', function () {
    $user = User::factory()->create();
    $email = $user->email;
    $proposal = Proposal::factory()->create(['status' => 'sent', 'client_email' => $email]);
    $this->actingAs($user)->get(route('proposals.public.show', $proposal))->assertOk();
    $user->update(['email' => 'changed@example.test']);
    $this->get(route('proposals.public.show', $proposal))->assertOk();
    $replacement = User::factory()->create(['email' => $email]);
    $this->actingAs($replacement)->get(route('proposals.public.show', $proposal))->assertNotFound();
    $user->delete();
    $this->get(route('proposals.public.show', $proposal))->assertNotFound();
});

it('transfers a pending document with audit and revokes the old account access', function () {
    $user = User::factory()->create();
    $new = User::factory()->create();
    $admin = Admin::factory()->create();
    $proposal = Proposal::factory()->create(['status' => 'sent', 'client_email' => $user->email]);
    $this->actingAs($user)->get(route('proposals.public.show', $proposal))->assertOk();
    $this->actingAs($admin, 'admin')->post(route('admin.proposals.transfer', $proposal), ['email' => $new->email, 'reason' => 'Client requested transfer'])->assertForbidden();
    $admin->givePermissionTo('proposals.transfer');
    $this->post(route('admin.proposals.transfer', $proposal), ['email' => $new->email, 'reason' => 'Client requested transfer'])->assertSessionHas('success');
    $this->actingAs($user, 'web')->get(route('proposals.public.show', $proposal))->assertNotFound();
    $this->actingAs($new, 'web')->get(route('proposals.public.show', $proposal))->assertOk();
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.recipient_transferred', 'subject_id' => $proposal->id]);
});

it('creates a draft revision and supersedes the prior sent revision only on publication', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['proposals.create', 'proposals.edit', 'proposals.view']);
    $source = Proposal::factory()->create(['status' => 'sent']);
    $this->actingAs($admin, 'admin')->post(route('admin.proposals.revise', $source))->assertSessionHas('success');
    $copy = Proposal::where('root_id', $source->id)->firstOrFail();
    expect($copy->revision)->toBe(2)->and($copy->status)->toBe('draft')->and($source->refresh()->superseded_at)->toBeNull();
    $payload = $copy->only(['number', 'type', 'status', 'title', 'client_name', 'client_email', 'currency', 'items', 'discount_minor', 'tax_rate_basis_points', 'terms', 'notes']) + ['issued_on' => '2026-11-16', 'valid_until' => '2026-11-30'];
    $this->put(route('admin.proposals.update', $copy), [...$payload, 'status' => 'sent'])->assertSessionHasNoErrors();
    expect($source->refresh()->displayStatus())->toBe('superseded');
    $this->put(route('admin.proposals.update', $source), [...$payload, 'number' => $source->number])->assertSessionHas('error');
    $this->get(route('admin.proposals.preview', $source))->assertOk()->assertInertia(fn ($page) => $page->where('preview', true)->has('proposal.history')->has('proposal.versions', 2));
});

it('records a verified immutable response receipt and blocks admin modifications', function () {
    $user = User::factory()->create();
    $proposal = Proposal::factory()->create(['status' => 'sent', 'client_email' => $user->email]);
    $this->actingAs($user);
    $code = workflowCode($proposal);
    $this->post(route('proposals.public.respond', $proposal), ['decision' => 'accepted', 'signer_name' => 'Client', 'response_note' => 'Agreed', 'verification_code' => $code, 'fingerprint' => $proposal->approvalFingerprint()])->assertSessionHas('success');
    expect($proposal->refresh()->accepted_snapshot['total_minor'])->toBe(100000);
    $this->get(route('proposals.public.show', $proposal))->assertInertia(fn ($page) => $page->where('proposal.response_note', 'Agreed')->where('proposal.status', 'accepted')->has('proposal.recipient_verified_at'));
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.receipt_sent', 'subject_id' => $proposal->id]);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['proposals.edit', 'proposals.transfer']);
    $payload = $proposal->only(['number', 'type', 'title', 'client_name', 'client_email', 'currency', 'items', 'discount_minor', 'tax_rate_basis_points', 'terms', 'notes']) + ['status' => 'sent', 'issued_on' => '2026-11-16', 'valid_until' => '2026-11-30'];
    $this->actingAs($admin, 'admin')->put(route('admin.proposals.update', $proposal), [...$payload, 'title' => 'Tampered agreement'])->assertSessionHas('error');
    expect($proposal->refresh()->title)->not->toBe('Tampered agreement');
    $this->post(route('admin.proposals.transfer', $proposal), ['email' => $user->email, 'reason' => 'Attempt completed transfer'])->assertStatus(409);
});

it('creates one linked invoice draft and requires issue before payment', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['proposals.view', 'invoices.create', 'invoices.payments.create']);
    $proposal = Proposal::factory()->create(['status' => 'accepted']);
    $this->actingAs($admin, 'admin');
    $payload = ['percentage' => 30, 'due_on' => '2026-11-30'];
    $this->post(route('admin.proposals.invoice', $proposal), $payload)->assertSessionHas('success');
    $this->post(route('admin.proposals.invoice', $proposal), $payload)->assertSessionHas('success');
    expect(Invoice::where('proposal_id', $proposal->id)->count())->toBe(1);
    $invoice = Invoice::where('proposal_id', $proposal->id)->firstOrFail();
    expect($invoice->status)->toBe('draft')->and($invoice->amount_minor)->toBe(30000)->and($invoice->currency)->toBe('BDT');
    $payment = ['amount_minor' => 10000, 'paid_on' => '2026-11-16'];
    $this->post(route('admin.invoices.payments.store', $invoice), $payment)->assertSessionHasErrors('amount_minor');
    $this->post(route('admin.invoices.issue', $invoice))->assertSessionHas('success');
    $this->post(route('admin.invoices.payments.store', $invoice), $payment)->assertSessionHasNoErrors();
});

it('sends a limited reminder once daily only for eligible pending documents', function () {
    Notification::fake();
    $pending = Proposal::factory()->create(['status' => 'sent']);
    $pending->forceFill(['invitation_sent_at' => now()->subDays(4)])->save();
    Proposal::factory()->create(['status' => 'accepted']);
    Proposal::factory()->create(['status' => 'sent', 'valid_until' => '2026-11-15']);
    $this->artisan('proposals:remind')->assertSuccessful();
    $this->artisan('proposals:remind')->assertSuccessful();
    Notification::assertCount(1);
    expect($pending->refresh()->last_reminded_at)->not->toBeNull();
});

it('reports invitation transport failures and records a retryable failure safely', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.edit');
    $proposal = Proposal::factory()->create(['status' => 'sent']);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Sensitive provider detail'));
    $this->actingAs($admin, 'admin')->post(route('admin.proposals.invite', $proposal))->assertSessionHas('error');
    expect($proposal->refresh()->invitation_status)->toBe('failed');
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.invitation_delivery.failed']);
    $this->assertDatabaseMissing('audit_logs', ['description' => 'Sensitive provider detail']);
});

it('does not send verification mail inside a database transaction', function () {
    $user = User::factory()->create();
    $proposal = Proposal::factory()->create(['status' => 'sent', 'client_email' => $user->email]);
    $level = DB::transactionLevel();
    $observedLevel = null;
    Notification::shouldReceive('send')->once()->andReturnUsing(function () use (&$observedLevel): void {
        $observedLevel = DB::transactionLevel();
    });
    $this->actingAs($user)->post(route('proposals.public.send-code', $proposal), ['fingerprint' => $proposal->refresh()->approvalFingerprint()])->assertSessionHas('success');
    expect($observedLevel)->toBe($level);
});

it('shows pending expiry and completion summaries and filters for the linked client', function () {
    $user = User::factory()->create();
    Proposal::factory()->create(['status' => 'sent', 'client_email' => $user->email, 'valid_until' => '2026-11-17']);
    Proposal::factory()->create(['status' => 'accepted', 'client_email' => $user->email]);
    Proposal::factory()->create(['status' => 'sent', 'client_email' => $user->email, 'valid_until' => '2026-11-15']);
    $this->actingAs($user)->get(route('client.proposals.index', ['status' => 'expiring']))->assertOk()->assertInertia(fn ($page) => $page->where('summary.pending', 1)->where('summary.expiring', 1)->where('summary.completed', 1)->has('documents.data', 1));
});

it('limits manual reminders and does not disclose linked invoices to proposal-only admins', function () {
    Notification::fake();
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['proposals.view', 'proposals.edit']);
    $proposal = Proposal::factory()->create(['status' => 'sent']);
    $this->actingAs($admin, 'admin')->post(route('admin.proposals.remind', $proposal))->assertSessionHas('success');
    $this->post(route('admin.proposals.remind', $proposal))->assertSessionHas('error');
    Notification::assertCount(1);
    Invoice::create(['proposal_id' => $proposal->id, 'number' => 'QA-INVOICE', 'customer_name' => 'Client', 'description' => 'Scope', 'amount_minor' => 100, 'currency' => 'BDT', 'issued_on' => '2026-11-16', 'due_on' => '2026-11-30', 'status' => 'draft']);
    $this->get(route('admin.proposals.preview', $proposal))->assertInertia(fn ($page) => $page->where('proposal.invoice', null));
});
