<?php

use App\Models\Admin;
use App\Models\Category;
use App\Notifications\AdminInviteNotification;
use App\Services\AuditLogService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('resends setup invitations only for eligible accounts and rejects invalidated tokens', function () {
    Notification::fake();
    $actor = Admin::factory()->create(['is_active' => true]);
    $recipient = Admin::factory()->create(['is_active' => true, 'must_change_password' => true]);
    $this->actingAs($actor, 'admin')->postJson(route('admin.users.invite', $recipient))->assertForbidden();
    $actor->givePermissionTo('users.invite');
    $this->post(route('admin.users.invite', $recipient))->assertSessionHas('success');
    Notification::assertSentTo($recipient, AdminInviteNotification::class, function ($notification) use ($recipient) {
        expect($notification->shouldSend($recipient, 'mail'))->toBeTrue();
        expect($notification->toMail($recipient)->actionUrl)->toContain('email='.urlencode($recipient->email));
        Password::broker('admins')->deleteToken($recipient);
        expect($notification->shouldSend($recipient, 'mail'))->toBeFalse();

        return true;
    });
    $recipient->update(['must_change_password' => false]);
    $this->post(route('admin.users.invite', $recipient))->assertSessionHas('error');
    Notification::assertSentToTimes($recipient, AdminInviteNotification::class, 1);
});

it('rolls category cascades back when their audit cannot be recorded', function () {
    $actor = Admin::factory()->create(['is_active' => true]);
    $actor->givePermissionTo('categories.edit');
    $parent = Category::create(['module' => 'finance', 'type' => 'expense', 'name' => 'Parent QA', 'is_active' => true]);
    $child = Category::create(['name' => 'Child QA', 'type' => 'expense', 'parent_id' => $parent->id, 'module' => $parent->module, 'is_active' => true]);
    $this->mock(AuditLogService::class)->shouldReceive('updated')->andThrow(new RuntimeException('audit unavailable'));
    $this->actingAs($actor, 'admin')->patchJson(route('admin.categories.update', $parent), ['name' => $parent->name, 'module' => $parent->module, 'type' => $parent->type, 'is_active' => false])->assertServerError();
    expect($parent->fresh()->is_active)->toBeTrue()->and($child->fresh()->is_active)->toBeTrue();
});
