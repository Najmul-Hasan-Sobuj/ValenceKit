<?php

use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Services\AdminNotifier;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

test('notification history is private paginated and permissions are rechecked', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $other = Admin::factory()->create();
    $admin->givePermissionTo('contact.view');
    $other->givePermissionTo('contact.view');
    foreach (range(1, 20) as $i) {
        $admin->notify(new AdminNotification('contact.view', 'New message', 'A new enquiry arrived.', 'admin.contact.index'));
    }
    $this->getJson(route('admin.notifications.index'))->assertUnauthorized();
    $first = $this->actingAs($admin, 'admin')->getJson(route('admin.notifications.index'))->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('unread_count', 20);
    $this->getJson(route('admin.notifications.index', ['cursor' => $first->json('next_cursor')]))->assertOk()->assertJsonCount(5, 'data');
    $id = $first->json('data.0.id');
    $this->actingAs($other, 'admin')->patchJson(route('admin.notifications.read', $id))->assertNotFound();
    $this->actingAs($admin, 'admin')->patchJson(route('admin.notifications.read', $id))->assertOk();
    $this->getJson(route('admin.notifications.index'))->assertJsonPath('unread_count', 19);
    $this->patchJson(route('admin.notifications.read-all'))->assertOk();
    $this->getJson(route('admin.notifications.index'))->assertJsonPath('unread_count', 0);
    $admin->revokePermissionTo('contact.view');
    $this->getJson(route('admin.notifications.index'))->assertJsonCount(0, 'data');
    $this->patchJson(route('admin.notifications.read', $id))->assertNotFound();
});

test('notifier targets only active admins with the required permission', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $allowed = Admin::factory()->create(['is_active' => true]);
    $allowed->givePermissionTo('contact.view');
    $inactive = Admin::factory()->create(['is_active' => false]);
    $inactive->givePermissionTo('contact.view');
    $other = Admin::factory()->create(['is_active' => true]);
    Notification::fake();
    app(AdminNotifier::class)->send(new AdminNotification('contact.view', 'New message', 'An enquiry arrived.', 'admin.contact.index'));
    Notification::assertSentTo($allowed, AdminNotification::class);
    Notification::assertNotSentTo([$inactive, $other], AdminNotification::class);
});

test('individual notification actions are scoped and keep unread counts accurate', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $other = Admin::factory()->create();
    $admin->givePermissionTo('contact.view');
    $other->givePermissionTo('contact.view');
    $admin->notify(new AdminNotification('contact.view', 'New message', 'An enquiry arrived.', 'admin.contact.index'));
    $id = $admin->notifications()->sole()->id;
    $this->deleteJson(route('admin.notifications.destroy', $id))->assertUnauthorized();
    $this->actingAs($other, 'admin')->deleteJson(route('admin.notifications.destroy', $id))->assertNotFound();
    $this->actingAs($admin, 'admin')->patchJson(route('admin.notifications.read', $id), ['read' => 'invalid'])->assertUnprocessable();
    $this->patchJson(route('admin.notifications.read', $id), ['read' => true])->assertOk()->assertJsonPath('unread_count', 0);
    $this->patchJson(route('admin.notifications.read', $id), ['read' => true])->assertOk()->assertJsonPath('unread_count', 0);
    $this->patchJson(route('admin.notifications.read', $id), ['read' => false])->assertOk()->assertJsonPath('read_at', null)->assertJsonPath('unread_count', 1);
    $admin->revokePermissionTo('contact.view');
    $this->deleteJson(route('admin.notifications.destroy', $id))->assertNotFound();
    $admin->givePermissionTo('contact.view');
    $this->deleteJson(route('admin.notifications.destroy', $id))->assertOk()->assertJsonPath('unread_count', 0);
    $this->assertDatabaseMissing('notifications', ['id' => $id]);
});

test('one notification creates one history record and one broadcast job', function () {
    Queue::fake([BroadcastEvent::class]);
    $admin = Admin::factory()->create();
    $admin->notify(new AdminNotification('users.view', 'Test notification', 'Hello', 'admin.dashboard'));
    expect($admin->notifications()->count())->toBe(1);
    Queue::assertPushed(BroadcastEvent::class, 1);
});

test('Reverb private channels authorize only the owning active admin', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
    require base_path('routes/channels.php');
    $admin = Admin::factory()->create(['is_active' => true]);
    $payload = ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.Admin.'.$admin->id];
    $this->actingAs(User::factory()->create())->postJson(route('admin.broadcasting.auth'), $payload)->assertUnauthorized();
    $this->actingAs($admin, 'admin')->postJson(route('admin.broadcasting.auth'), $payload)->assertOk()->assertJsonStructure(['auth']);
    $this->postJson(route('admin.broadcasting.auth'), [...$payload, 'channel_name' => 'private-App.Models.Admin.'.($admin->id + 1)])->assertForbidden();
    $admin->update(['is_active' => false]);
    $this->postJson(route('admin.broadcasting.auth'), $payload)->assertForbidden();
});

test('contact messages notify admins while historical imports and outgoing replies stay silent', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create(['is_active' => true]);
    $admin->givePermissionTo('contact.view');
    Notification::fake();

    $contact = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    Notification::assertSentTo($admin, AdminNotification::class, fn ($notification) => $notification->title === 'New contact message');
    $imported = ContactMessage::create(['name' => 'Client', 'email' => 'client@example.com', 'message' => '', 'initiated_by_admin' => true]);
    $historical = $imported->replies()->make(['incoming' => true, 'body' => 'Historical message']);
    $historical->created_at = now()->subDay();
    $historical->save();
    $contact->replies()->create(['body' => 'Outgoing reply']);
    Notification::assertSentTimes(AdminNotification::class, 1);
    $contact->replies()->create(['incoming' => true, 'body' => 'New reply']);
    Notification::assertSentTimes(AdminNotification::class, 2);
});

test('normal users cannot access admin notification endpoints even with a matching account id', function () {
    $admin = Admin::factory()->create(['name' => '[QA Test] Notification owner']);
    $admin->notify(new AdminNotification('users.view', '[QA Test] Private notification', 'Admin only.', 'admin.dashboard'));
    $id = $admin->notifications()->sole()->id;
    $user = User::factory()->create(['id' => $admin->id, 'name' => '[QA Test] Normal user']);
    $this->actingAs($user, 'web')->getJson(route('admin.notifications.index'))->assertUnauthorized();
    $this->patchJson(route('admin.notifications.read', $id))->assertUnauthorized();
    $this->patchJson(route('admin.notifications.read-all'))->assertUnauthorized();
    $this->deleteJson(route('admin.notifications.destroy', $id))->assertUnauthorized();
    $this->postJson(route('admin.broadcasting.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.Admin.'.$admin->id])->assertUnauthorized();
    expect($admin->notifications()->sole()->read_at)->toBeNull();
});

test('role permission changes immediately filter notification history and actions without affecting another admin', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $role = Role::create(['name' => '[QA Test] Notification viewer', 'guard_name' => 'admin']);
    $role->givePermissionTo('contact.view');
    $alpha = Admin::factory()->create(['name' => '[QA Test] Alpha']);
    $alpha->assignRole($role);
    $beta = Admin::factory()->create(['name' => '[QA Test] Beta']);
    $beta->givePermissionTo('contact.view');
    foreach ([$alpha, $beta] as $admin) {
        $admin->notify(new AdminNotification('contact.view', '[QA Test] Contact', 'A new enquiry.', 'admin.contact.index'));
    }
    $id = $alpha->notifications()->sole()->id;
    $this->actingAs($alpha, 'admin')->getJson(route('admin.notifications.index'))->assertJsonPath('unread_count', 1);
    $role->revokePermissionTo('contact.view');
    $this->actingAs($alpha->fresh(), 'admin')->getJson(route('admin.notifications.index'))->assertJsonCount(0, 'data')->assertJsonPath('unread_count', 0);
    $this->patchJson(route('admin.notifications.read', $id))->assertNotFound();
    $this->deleteJson(route('admin.notifications.destroy', $id))->assertNotFound();
    $role->givePermissionTo('contact.view');
    $this->actingAs($alpha->fresh(), 'admin')->getJson(route('admin.notifications.index'))->assertJsonCount(1, 'data');
    $this->actingAs($beta, 'admin')->getJson(route('admin.notifications.index'))->assertJsonPath('unread_count', 1);
});

test('inactive admins cannot read or mutate notification history', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->inactive()->create(['name' => '[QA Test] Inactive']);
    $admin->givePermissionTo('contact.view');
    $admin->notify(new AdminNotification('contact.view', '[QA Test] Private notification', 'Hidden for inactive accounts.', 'admin.contact.index'));
    $id = $admin->notifications()->sole()->id;
    $this->actingAs($admin, 'admin')->getJson(route('admin.notifications.index'))->assertForbidden();
    $this->actingAs($admin, 'admin')->patchJson(route('admin.notifications.read', $id))->assertForbidden();
    $this->actingAs($admin, 'admin')->deleteJson(route('admin.notifications.destroy', $id))->assertForbidden();
    expect($admin->notifications()->sole()->read_at)->toBeNull();
});
