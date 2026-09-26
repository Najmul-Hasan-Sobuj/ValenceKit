<?php

use App\Models\Admin;
use App\Notifications\AdminInviteNotification;
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
        return $notification instanceof ShouldQueue;
    });
});
