<?php

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Employee;
use App\Models\McpUsageLog;
use App\Models\Module;
use App\Models\Proposal;
use Database\Seeders\RoleAndPermissionSeeder;
use DriveSnap\Models\Backup;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('limits the activity monitor to the latest ten records and respects access', function () {
    $admin = Admin::factory()->create();
    foreach (range(1, 12) as $number) {
        AuditLog::create(['action' => 'test.event.'.$number]);
    }
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('recentActivity', null));
    $admin->givePermissionTo('audit.view');
    $this->get(route('admin.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('recentActivity', 10)
            ->where('recentActivity.0.action', 'test.event.12')
            ->where('recentActivity.9.action', 'test.event.3'));
    Module::create(['key' => 'audit', 'enabled' => false]);
    $this->get(route('admin.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('recentActivity', null));
});

it('shares inbox counts and updates unread state only for messages actually seen', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('contact.view');
    $contact = ContactMessage::create(['name' => 'Customer', 'email' => 'customer@example.com', 'message' => 'Hello']);
    $reply = $contact->replies()->create(['body' => 'Incoming', 'incoming' => true]);
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('contacts.unread', 1)->has('contacts.latest', 1));

    $this->postJson(route('admin.contact.read', $contact), ['reply_id' => $reply->id])->assertOk();
    expect(ContactMessage::unread()->count())->toBe(0);
    $newReply = $contact->replies()->create(['body' => 'Another incoming', 'incoming' => true]);
    expect(ContactMessage::unread()->count())->toBe(1);
    $this->postJson(route('admin.contact.read', $contact), ['reply_id' => $reply->id])->assertOk();
    expect(ContactMessage::unread()->count())->toBe(1);
    $this->postJson(route('admin.contact.read', $contact), ['reply_id' => $newReply->id])->assertOk();
    expect(ContactMessage::unread()->count())->toBe(0);
});

it('protects read receipts from unauthorized admins and unrelated reply ids', function () {
    $admin = Admin::factory()->create();
    $contact = ContactMessage::create(['name' => 'One', 'email' => 'one@example.com', 'message' => 'Hello']);
    $other = ContactMessage::create(['name' => 'Two', 'email' => 'two@example.com', 'message' => 'Hello']);
    $reply = $other->replies()->create(['body' => 'Other thread', 'incoming' => true]);
    $this->actingAs($admin, 'admin')->postJson(route('admin.contact.read', $contact))->assertForbidden();
    $admin->givePermissionTo('contact.view');
    $this->postJson(route('admin.contact.read', $contact), ['reply_id' => $reply->id])->assertNotFound();
    expect($contact->fresh()->read_at)->toBeNull();
});

it('hides operational widgets when modules are disabled', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');
    foreach (['contact', 'proposals', 'people', 'backups', 'mcp'] as $module) {
        Module::create(['key' => $module, 'enabled' => false]);
    }
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts', null)->missing('proposals')->missing('people')
            ->where('backupOverview', null)->where('mcpStats', null));
});

it('does not load the removed HR overview even with HR access', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('hr.self');
    Employee::factory()->create(['admin_id' => $admin->id, 'is_active' => true]);
    Employee::factory()->create(['is_active' => true]);
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('people')->where('contacts', null)->where('backupOverview', null));
});

it('shows backup storage badges without exposing private paths or drive ids', function () {
    Storage::fake('drivesnap');
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['backups.view', 'backups.download']);
    $backup = Backup::create(['status' => 'completed', 'path' => 'backups/example.enc', 'drive_file_id' => 'private-drive-id', 'completed_at' => now()]);
    Storage::disk('drivesnap')->put('backups/example.enc', 'test');
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('backupOverview.latest.name', 'example.enc')
            ->where('backupOverview.latest.location', 'Both')
            ->missing('backupOverview.latest.path')->missing('backupOverview.latest.drive_file_id'));
    $backup->update(['drive_file_id' => null]);
    $this->get(route('admin.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('backupOverview.latest.location', 'Local'));
    $backup->update(['drive_file_id' => 'private-drive-id']);
    Storage::disk('drivesnap')->delete('backups/example.enc');
    $this->get(route('admin.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('backupOverview.latest.location', 'Drive'));
    $admin->revokePermissionTo('backups.download');
    $this->get(route('admin.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('backupOverview.latest.location', null));
});

it('does not load removed proposal and finance lookup data', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('proposals.view');
    Proposal::factory()->create(['status' => 'sent']);
    Proposal::factory()->create(['status' => 'sent', 'valid_until' => today()->subDay()]);
    Proposal::factory()->create(['status' => 'draft']);

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('proposals')
            ->missing('financeCategories')
            ->missing('paymentMethods')
            ->where('contacts', null)
            ->missing('people')
            ->where('backupOverview', null));
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

it('does not include mcpStats if admin lacks permission', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('mcpStats', null)
            ->where('platformStats', null)
        );
});

it('shares platform statistics only with admins allowed to view users', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('users.view');

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('platformStats', fn (Assert $stats) => $stats
                ->where('total_admins', 1)
                ->etc()
            )
            ->where('mcpStats', null)
        );
});

it('renders mcpStats with total_calls and calls_today when permitted', function () {
    $superAdmin = Admin::factory()->create();
    $superAdmin->assignRole('Super Admin');

    McpUsageLog::create([
        'rpc_method' => 'tools/call',
        'token_used' => 'bearer_oauth_key',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PhpUnitTest',
        'endpoint' => 'database-query',
        'created_at' => now(),
    ]);

    $log2 = McpUsageLog::create([
        'rpc_method' => 'tools/call',
        'token_used' => 'passport_token',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PhpUnitTest',
        'endpoint' => 'search-docs',
    ]);
    $log2->timestamps = false;
    $log2->created_at = now()->subDays(2);
    $log2->save();

    foreach (['initialize', 'tools/list', null] as $method) {
        McpUsageLog::create(['token_used' => 'test', 'endpoint' => '/mcp/valencekit', 'rpc_method' => $method]);
    }

    $this->actingAs($superAdmin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->has('mcpStats', fn (Assert $stats) => $stats
                ->where('total_calls', 2)
                ->where('calls_today', 1)
                ->has('daily', 14)
                ->missing('total_tokens')
                ->missing('tokens_today')
            )
        );
});
