<?php

use App\Jobs\DeliverMail;
use App\Models\Admin;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\ProposalStatusNotification;
use App\Services\AuditLogService;
use App\Services\MailDeliveryService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('queues one scoped delivery and cancels stale recipient or document data before sending', function () {
    Queue::fake();
    Notification::fake();
    $user = User::factory()->create();
    $proposal = Proposal::factory()->create(['status' => 'sent', 'client_email' => $user->email]);
    $mail = new ProposalStatusNotification($proposal->number, 'Review the current document', route('proposals.public.show', $proposal));
    $service = app(MailDeliveryService::class);
    $delivery = $service->queue($proposal, $user, $mail, 'proposal.reminder_sent', deduplicationKey: 'today');
    $same = $service->queue($proposal, $user, $mail, 'proposal.reminder_sent', deduplicationKey: 'today');
    expect($same->id)->toBe($delivery->id);
    Queue::assertPushed(DeliverMail::class, 1);
    expect(DB::table('mail_deliveries')->value('email'))->not->toBe($user->email);
    $proposal->update(['title' => 'Revised scope']);
    (new DeliverMail($delivery->id))->handle(app(AuditLogService::class));
    expect($delivery->refresh()->status)->toBe('cancelled');
    Notification::assertNothingSent();
});

it('delivers once and records transport acceptance rather than claiming inbox receipt', function () {
    Queue::fake();
    Notification::fake();
    $user = User::factory()->create();
    $proposal = Proposal::factory()->create(['client_email' => $user->email]);
    $mail = new ProposalStatusNotification($proposal->number, 'Receipt', route('proposals.public.show', $proposal));
    $delivery = app(MailDeliveryService::class)->queue($proposal, $user, $mail, 'proposal.receipt_sent');
    $job = new DeliverMail($delivery->id);
    $job->handle(app(AuditLogService::class));
    $job->handle(app(AuditLogService::class));
    Notification::assertSentToTimes($user, ProposalStatusNotification::class, 1);
    expect($delivery->refresh()->status)->toBe('sent')->and($delivery->attempts)->toBe(1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'proposal.receipt_sent', 'subject_id' => $proposal->id]);
});

it('does not dispatch delivery after a business transaction rolls back', function () {
    Queue::fake();
    $proposal = Proposal::factory()->create();
    try {
        DB::transaction(function () use ($proposal): void {
            app(MailDeliveryService::class)->queue($proposal, $proposal->client_email, new ProposalStatusNotification($proposal->number, 'Pending', 'https://example.test'), 'proposal.reminder_sent');
            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }
    $this->assertDatabaseCount('mail_deliveries', 0);
    Queue::assertNothingPushed();
});

it('stores encrypted job payloads and redacts provider failures while permitting retry', function () {
    $user = User::factory()->create();
    $proposal = Proposal::factory()->create(['client_email' => $user->email]);
    config(['queue.default' => 'database']);
    $mail = new ProposalStatusNotification($proposal->number, 'qa-sensitive-message', 'https://example.test');
    $delivery = app(MailDeliveryService::class)->queue($proposal, $user, $mail, 'proposal.receipt_sent');
    $payload = DB::table('jobs')->value('payload');
    expect($payload)->not->toContain('qa-sensitive-message');
    Notification::shouldReceive('sendNow')->once()->andThrow(new RuntimeException('qa-private-provider-detail'));
    $job = new DeliverMail($delivery->id);
    expect(fn () => $job->handle(app(AuditLogService::class)))->toThrow(RuntimeException::class, 'Email delivery failed; retry is scheduled.');
    expect($delivery->refresh()->status)->toBe('retrying')->and($delivery->error_code)->toBe('mail_transport_failed');
    $job->failed(new RuntimeException('qa-private-provider-detail'));
    expect($delivery->refresh()->status)->toBe('failed');
    $this->assertDatabaseMissing('audit_logs', ['description' => 'qa-private-provider-detail']);
});

it('recovers committed mail after dispatch failure without exposing its message', function () {
    Queue::fake();
    Notification::fake();
    $proposal = Proposal::factory()->create();
    $delivery = app(MailDeliveryService::class)->queue($proposal, $proposal->client_email, new ProposalStatusNotification($proposal->number, 'private recovery message', 'https://example.test'), 'proposal.receipt_sent');
    expect(DB::table('mail_deliveries')->where('id', $delivery->id)->value('notification'))->not->toContain('private recovery message');
    $delivery->update(['status' => 'pending', 'error_code' => 'queue_dispatch_failed', 'updated_at' => now()->subMinutes(3)]);
    $this->artisan('mail:recover-pending')->assertSuccessful();
    expect($delivery->fresh()->status)->toBe('queued');
    (new DeliverMail($delivery->id))->handle(app(AuditLogService::class));
    expect($delivery->fresh()->status)->toBe('sent');
});

it('cancels queued mail when the administrator loses access', function (string $change) {
    $this->seed(RoleAndPermissionSeeder::class);
    Queue::fake();
    Notification::fake();
    $admin = Admin::factory()->create(['is_active' => true]);
    $admin->givePermissionTo('proposals.view');
    $proposal = Proposal::factory()->create();
    $delivery = app(MailDeliveryService::class)->queue($proposal, $admin, new ProposalStatusNotification($proposal->number, 'Update', 'https://example.test'), 'proposal.creator_notified', 'proposals.view');
    match ($change) {
        'permission' => $admin->revokePermissionTo('proposals.view'),
        'email' => $admin->update(['email' => 'changed@example.test']),
        'inactive' => $admin->update(['is_active' => false]),
    };
    (new DeliverMail($delivery->id))->handle(app(AuditLogService::class));
    expect($delivery->fresh()->status)->toBe('cancelled');
    Notification::assertNothingSent();
})->with(['permission', 'email', 'inactive']);
