<?php

use App\Models\Admin;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Notifications\UserNotification;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Queue;

test('user notification history is private cursor paginated and excludes unrelated notification types', function () {
    $user = User::factory()->create();
    foreach (range(1, 18) as $i) {
        $user->notify(new UserNotification('Update '.$i, 'Your account update.', 'profile.edit'));
    }
    $user->notify(new AdminNotification('users.view', 'Admin only', 'Hidden.', 'admin.dashboard'));
    $this->getJson(route('notifications.index'))->assertUnauthorized();
    $first = $this->actingAs($user)->getJson(route('notifications.index'))->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('unread_count', 18)->assertJsonPath('data.0.url', '/settings/profile');
    $this->getJson(route('notifications.index', ['cursor' => $first->json('next_cursor')]))->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('next_cursor', null);
    $this->actingAs(User::factory()->create())->getJson(route('notifications.index'))->assertJsonCount(0, 'data')->assertJsonPath('unread_count', 0);
});

test('user notification actions validate input and preserve accurate unread counts', function () {
    $user = User::factory()->create();
    $user->notify(new UserNotification('Account update', 'Hello.', 'dashboard'));
    $id = $user->notifications()->sole()->id;
    $this->actingAs($user)->patchJson(route('notifications.read', $id), ['read' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('read');
    $this->patchJson(route('notifications.read', $id))->assertOk()->assertJsonPath('unread_count', 0);
    expect($user->notifications()->sole()->read_at)->not->toBeNull();
    $this->patchJson(route('notifications.read', $id), ['read' => false])->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('read_at', null);
    $this->patchJson(route('notifications.read-all'))->assertOk()->assertJsonPath('unread_count', 0);
    expect($user->notifications()->sole()->read_at)->not->toBeNull();
    $this->deleteJson(route('notifications.destroy', $id))->assertOk()->assertJsonPath('unread_count', 0);
    $this->assertDatabaseMissing('notifications', ['id' => $id]);
});

test('users cannot change another user or admin notification even with matching numeric account ids', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $admin = Admin::factory()->create(['id' => $other->id]);
    $owner->notify(new UserNotification('Private user update', 'Private.', 'dashboard'));
    $admin->notify(new AdminNotification('users.view', 'Private admin update', 'Private.', 'admin.dashboard'));
    foreach ([$owner->notifications()->sole()->id, $admin->notifications()->sole()->id] as $id) {
        $this->actingAs($other)->patchJson(route('notifications.read', $id))->assertNotFound();
        $this->deleteJson(route('notifications.destroy', $id))->assertNotFound();
    }
    $this->patchJson(route('notifications.read-all'))->assertOk();
    expect($owner->notifications()->sole()->read_at)->toBeNull();
    expect($admin->notifications()->sole()->read_at)->toBeNull();
});

test('unverified users and admin-only sessions cannot use web notification endpoints', function () {
    $user = User::factory()->unverified()->create();
    $user->notify(new UserNotification('Update', 'Hello.', 'dashboard'));
    $id = $user->notifications()->sole()->id;
    $this->actingAs($user)->getJson(route('notifications.index'))->assertForbidden();
    $this->patchJson(route('notifications.read', $id))->assertForbidden();
    $this->deleteJson(route('notifications.destroy', $id))->assertForbidden();
    $this->patchJson(route('notifications.read-all'))->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->actingAs(Admin::factory()->create(), 'admin')->getJson(route('notifications.index'))->assertUnauthorized();
});

test('user private channels reject other users and admin channels', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
    require base_path('routes/channels.php');
    $user = User::factory()->create();
    $other = User::factory()->create();
    $payload = ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.'.$user->id];
    $this->postJson(route('broadcasting.auth'), $payload)->assertUnauthorized();
    $this->actingAs($user)->postJson(route('broadcasting.auth'), $payload)->assertOk()->assertJsonStructure(['auth']);
    $this->postJson(route('broadcasting.auth'), [...$payload, 'channel_name' => 'private-App.Models.User.'.$other->id])->assertForbidden();
    $this->postJson(route('broadcasting.auth'), [...$payload, 'channel_name' => 'private-App.Models.Admin.'.$user->id])->assertForbidden();
    $this->actingAs($user->forceFill(['email_verified_at' => null]))->postJson(route('broadcasting.auth'), $payload)->assertForbidden();
});

test('user notification stores one record and queues one minimal private broadcast', function () {
    Queue::fake([BroadcastEvent::class]);
    $user = User::factory()->create();
    $notification = new UserNotification('Account update', 'Private content.', 'dashboard');
    $user->notify($notification);
    expect($user->notifications()->sole()->data['title'])->toBe('Account update');
    expect($notification->toBroadcast($user)->data)->toBe(['id' => $notification->id]);
    Queue::assertPushed(BroadcastEvent::class, 1);
});
