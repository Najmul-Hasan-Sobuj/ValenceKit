<?php

use App\Models\Admin;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\ProposalVerificationCode;
use Database\Seeders\ProposalSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('seeds reusable demo documents with public identifiers when model events are disabled', function () {
    Model::withoutEvents(fn () => $this->seed(ProposalSeeder::class));
    $this->assertDatabaseCount('proposals', 30);
    $identifiers = Proposal::orderBy('number')->pluck('public_id')->all();
    expect(Proposal::whereNotNull('public_id')->distinct()->count('public_id'))->toBe(30);
    expect(Proposal::where('type', 'quotation')->count())->toBe(10);
    expect(Proposal::where('type', 'contract')->count())->toBe(10);

    Model::withoutEvents(fn () => $this->seed(ProposalSeeder::class));
    $this->assertDatabaseCount('proposals', 30);
    expect(Proposal::orderBy('number')->pluck('public_id')->all())->toBe($identifiers);
});

function proposalVerificationCode(Proposal $proposal): string
{
    Notification::fake();
    test()->post(route('proposals.public.send-code', $proposal), ['fingerprint' => $proposal->refresh()->approvalFingerprint()])->assertRedirect()->assertSessionHasNoErrors();
    $code = '';
    Notification::assertSentOnDemand(ProposalVerificationCode::class, function ($notification, $channels, $notifiable) use (&$code, $proposal) {
        $code = $notification->code;

        return $notifiable->routes['mail'] === $proposal->client_email;
    });

    return $code;
}

function proposalPayload(array $overrides = []): array
{
    return [...[
        'number' => 'PROP-TEST-001', 'type' => 'proposal', 'status' => 'draft',
        'title' => 'Website redesign proposal', 'client_name' => 'Acme Limited',
        'client_email' => 'client@example.com', 'currency' => 'BDT',
        'items' => [
            ['description' => 'Discovery', 'quantity' => 2, 'unit_price_minor' => 10000],
            ['description' => 'Implementation', 'quantity' => 1.5, 'unit_price_minor' => 20000],
        ],
        'discount_minor' => 5000, 'tax_rate_basis_points' => 1000,
        'issued_on' => '2026-11-16', 'valid_until' => '2026-11-30',
        'terms' => 'Fifty percent deposit required.', 'notes' => 'Thank you for the opportunity.',
    ], ...$overrides];
}

it('requires the matching permission for every admin action', function () {
    $admin = Admin::factory()->create();
    $proposal = Proposal::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.proposals.index'))->assertForbidden();
    $this->postJson(route('admin.proposals.store'), proposalPayload())->assertForbidden();
    $this->putJson(route('admin.proposals.update', $proposal), proposalPayload())->assertForbidden();
    $this->deleteJson(route('admin.proposals.destroy', $proposal))->assertForbidden();
});

it('creates a document and calculates trusted totals on the server', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.create');

    $this->actingAs($admin, 'admin')->post(route('admin.proposals.store'), proposalPayload([
        'subtotal_minor' => 1, 'tax_minor' => 1, 'total_minor' => 1,
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $proposal = Proposal::where('number', 'PROP-TEST-001')->firstOrFail();
    expect($proposal->subtotal_minor)->toBe(50000)
        ->and($proposal->discount_minor)->toBe(5000)
        ->and($proposal->tax_minor)->toBe(4500)
        ->and($proposal->total_minor)->toBe(49500)
        ->and($proposal->created_by)->toBe($admin->id);
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.created', 'subject_id' => $proposal->id]);
});

it('rejects invalid line items, dates, types, statuses, and excessive discounts', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.create');

    $this->actingAs($admin, 'admin')->postJson(route('admin.proposals.store'), proposalPayload([
        'type' => 'invoice', 'status' => 'accepted', 'items' => [],
        'discount_minor' => 100000, 'valid_until' => '2026-11-01',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['type', 'status', 'items', 'valid_until']);
    $this->postJson(route('admin.proposals.store'), proposalPayload(['discount_minor' => 100000]))
        ->assertUnprocessable()->assertJsonValidationErrors('discount_minor');
    $this->postJson(route('admin.proposals.store'), proposalPayload(['items' => [
        ['description' => 'Overflow', 'quantity' => 1000000, 'unit_price_minor' => 100000000000],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('items');
    $this->postJson(route('admin.proposals.store'), proposalPayload(['items' => [
        ['description' => 'Unexpected field', 'quantity' => 1, 'unit_price_minor' => 100, 'approved' => true],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('items.0');
});

it('filters and paginates documents for an authorized admin', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.view');
    Proposal::factory()->count(16)->create(['client_name' => 'Acme Limited', 'type' => 'quotation']);
    Proposal::factory()->create(['client_name' => 'Other Client', 'type' => 'contract']);

    $this->actingAs($admin, 'admin')->get(route('admin.proposals.index', ['search' => 'Acme', 'type' => 'quotation', 'page' => 2]))
        ->assertOk()->assertInertia(fn ($page) => $page->component('admin/proposals/Index')
        ->where('proposals.meta.total', 16)->has('proposals.data', 1)
        ->where('filters.search', 'Acme')->where('filters.type', 'quotation'));
});

it('updates and deletes an editable document with audit history', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['proposals.edit', 'proposals.delete']);
    $proposal = Proposal::factory()->create(['number' => 'PROP-OLD']);

    $this->actingAs($admin, 'admin')->put(route('admin.proposals.update', $proposal), proposalPayload(['number' => 'PROP-NEW']))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($proposal->refresh()->number)->toBe('PROP-NEW');
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.updated', 'subject_id' => $proposal->id]);

    $this->delete(route('admin.proposals.destroy', $proposal))->assertRedirect();
    $this->assertModelMissing($proposal);
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.deleted', 'subject_id' => $proposal->id]);
});

it('only exposes sent documents and records one signed client response', function () {
    $draft = Proposal::factory()->create(['status' => 'draft']);
    $sent = Proposal::factory()->create(['status' => 'sent', 'valid_until' => '2026-11-30']);
    $draft->update(['client_email' => $sent->client_email]);
    $this->actingAs(User::factory()->create(['email' => $sent->client_email]), 'web');

    $this->get(route('proposals.public.show', $draft))->assertNotFound();
    $this->get(route('proposals.public.show', $sent))->assertOk()->assertInertia(fn ($page) => $page
        ->component('public/Proposal')->where('proposal.number', $sent->number)->where('proposal.status', 'sent'));
    $this->post(route('proposals.public.respond', $sent), ['decision' => 'accepted'])->assertSessionHasErrors('signer_name');
    $code = proposalVerificationCode($sent);
    $this->post(route('proposals.public.respond', $sent), ['decision' => 'accepted', 'signer_name' => 'Jane Client', 'verification_code' => $code, 'fingerprint' => $sent->approvalFingerprint()])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($sent->refresh()->status)->toBe('accepted')
        ->and($sent->signer_name)->toBe('Jane Client')
        ->and($sent->responded_at)->not->toBeNull();
    expect($sent->recipient_verification)->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.recipient_verified', 'subject_id' => $sent->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.responded', 'subject_id' => $sent->id]);
    $this->post(route('proposals.public.respond', $sent), ['decision' => 'rejected', 'verification_code' => $code, 'fingerprint' => $sent->approvalFingerprint()])->assertSessionHas('error');
    expect($sent->refresh()->status)->toBe('accepted');
});

it('prevents responses to expired documents and deletion of accepted documents', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $expired = Proposal::factory()->create(['status' => 'sent', 'valid_until' => '2026-11-15']);
    $validToday = Proposal::factory()->create(['status' => 'sent', 'valid_until' => '2026-11-16']);
    $expired->update(['client_email' => $validToday->client_email]);
    $this->actingAs(User::factory()->create(['email' => $validToday->client_email]), 'web');
    $accepted = Proposal::factory()->create(['status' => 'accepted']);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.delete');

    $this->post(route('proposals.public.respond', $expired), ['decision' => 'rejected', 'verification_code' => '000000', 'fingerprint' => $expired->approvalFingerprint()])->assertSessionHas('error');
    expect($expired->refresh()->status)->toBe('sent');
    $code = proposalVerificationCode($validToday);
    $this->post(route('proposals.public.respond', $validToday), ['decision' => 'accepted', 'signer_name' => 'Valid Today', 'verification_code' => $code, 'fingerprint' => $validToday->approvalFingerprint()])
        ->assertSessionHasNoErrors();
    expect($validToday->refresh()->status)->toBe('accepted');
    $this->actingAs($admin, 'admin')->delete(route('admin.proposals.destroy', $accepted))->assertSessionHas('error');
    $this->assertModelExists($accepted);
});

it('requires an email code and rejects expired, changed, and exhausted challenges', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $proposal = Proposal::factory()->create(['status' => 'sent', 'valid_until' => '2026-11-30']);
    $this->actingAs(User::factory()->create(['email' => $proposal->client_email]), 'web');
    $payload = ['decision' => 'accepted', 'signer_name' => 'Client', 'fingerprint' => $proposal->refresh()->approvalFingerprint()];
    $this->post(route('proposals.public.respond', $proposal), $payload)->assertSessionHasErrors('verification_code');
    $code = proposalVerificationCode($proposal);
    expect($proposal->refresh()->recipient_verification['hash'])->not->toBe($code);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('proposals.public.respond', $proposal), [...$payload, 'verification_code' => '000000'])->assertSessionHasErrors('verification_code');
    }
    $this->post(route('proposals.public.respond', $proposal), [...$payload, 'verification_code' => $code])->assertSessionHasErrors('verification_code');
    expect($proposal->refresh()->status)->toBe('sent');

    $this->travel(61)->seconds();
    $code = proposalVerificationCode($proposal);
    $proposal->update(['title' => 'Changed scope']);
    $this->post(route('proposals.public.respond', $proposal), [...$payload, 'verification_code' => $code])->assertSessionHasErrors('verification_code');

    $this->travel(61)->seconds();
    $code = proposalVerificationCode($proposal);
    $payload['fingerprint'] = $proposal->approvalFingerprint();
    $this->travel(11)->minutes();
    $this->post(route('proposals.public.respond', $proposal), [...$payload, 'verification_code' => $code])->assertSessionHasErrors('verification_code');
    expect($proposal->refresh()->status)->toBe('sent');
});
