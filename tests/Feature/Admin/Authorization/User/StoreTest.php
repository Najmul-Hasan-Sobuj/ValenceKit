<?php

use App\Models\Admin;
use App\Notifications\AdminInviteNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);

    $this->admin = Admin::factory()->create();
    $role = Role::create(['name' => 'Store Users Role', 'guard_name' => 'admin']);
    $role->givePermissionTo(Permission::where('name', 'users.invite')->first());
    $this->admin->assignRole($role);
});

it('can store a new user and queues invitation notification', function () {
    Notification::fake();

    $role = Role::firstOrCreate(['name' => 'Support', 'guard_name' => 'admin']);
    $this->admin->givePermissionTo($role->permissions);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'Support',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('admins', [
        'email' => 'test@example.com',
        'name' => 'Test User',
        'is_active' => true,
    ]);

    $user = Admin::where('email', 'test@example.com')->first();
    expect($user->hasRole('Support'))->toBeTrue();

    Notification::assertSentTo($user, AdminInviteNotification::class, function ($notification) {
        return $notification instanceof ShouldQueue && $notification instanceof ShouldBeEncrypted;
    });
});

it('lets an invited administrator set a password once using the invitation token', function () {
    Notification::fake();
    $role = Role::where('name', 'Support')->firstOrFail();
    $this->admin->givePermissionTo($role->permissions);

    $this->actingAs($this->admin, 'admin')->post(route('admin.users.store'), [
        'name' => 'Invited', 'email' => 'invited@example.com', 'role' => $role->name,
    ])->assertRedirect();

    $invited = Admin::where('email', 'invited@example.com')->firstOrFail();
    $token = Notification::sent($invited, AdminInviteNotification::class)->first()->token;
    auth('admin')->logout();
    $payload = ['email' => $invited->email, 'token' => $token, 'password' => 'new-secret-password', 'password_confirmation' => 'new-secret-password'];

    $this->post(route('admin.password.update'), $payload)->assertSessionHasNoErrors();
    expect(password_verify('new-secret-password', $invited->fresh()->password))->toBeTrue();
    $this->post(route('admin.password.update'), $payload)->assertSessionHasErrors('email');
});

it('rejects assigning a role with permissions the inviter does not have', function () {
    $role = Role::create(['name' => 'Privileged', 'guard_name' => 'admin']);
    $role->givePermissionTo('roles.edit');

    $this->actingAs($this->admin, 'admin')->post(route('admin.users.store'), [
        'name' => 'New Admin', 'email' => 'new-admin@example.com', 'role' => $role->name,
    ])->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('admins', ['email' => 'new-admin@example.com']);
});

it('rejects invalid role values when inviting an administrator', function ($role) {
    $this->actingAs($this->admin, 'admin')->post(route('admin.users.store'), [
        'name' => 'New Admin', 'email' => 'new-admin@example.com', 'role' => $role,
    ])->assertSessionHasErrors('role');
})->with(['unknown role' => 'Not a role', 'invalid type' => [['invalid']]]);
