<?php

use App\Models\Admin;
use Database\Seeders\RoleAndPermissionSeeder;
use DriveSnap\Jobs\CreateBackup;
use DriveSnap\Models\Backup;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

it('uses ValenceKit admin permissions and records safe backup audit entries', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Bus::fake();
    $admin = Admin::factory()->create(['is_active' => true]);
    $this->actingAs($admin, 'admin')->get(route('drivesnap.index'))->assertForbidden();
    $admin->givePermissionTo('backups.view');
    $this->get(route('drivesnap.index'))->assertOk()->assertInertia(fn ($page) => $page->component('admin/backups/Index')->where('drivesnap.can.view', true)->where('drivesnap.can.create', false));
    $this->postJson(route('drivesnap.store'), ['upload_to_drive' => false])->assertForbidden();
    $admin->givePermissionTo('backups.create');
    $this->postJson(route('drivesnap.store'), ['upload_to_drive' => false])->assertAccepted();
    Bus::assertDispatched(CreateBackup::class);
    $this->assertDatabaseCount('drivesnap_backups', 1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'drivesnap.requested', 'actor_id' => $admin->id, 'subject_id' => null]);
    $this->getJson(route('drivesnap.status'))->assertOk()->assertDontSee('refresh_token')->assertDontSee('upload_url');
    $admin->update(['is_active' => false]);
    $this->get(route('drivesnap.index'))->assertRedirect(route('admin.login'));
});

it('attributes a download audit entry to the acting admin instead of the original requester', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('drivesnap');
    $requester = Admin::factory()->create(['is_active' => true]);
    $downloader = Admin::factory()->create(['is_active' => true]);
    $downloader->givePermissionTo(['backups.view', 'backups.download']);
    $archive = 'encrypted-download-fixture';
    Storage::disk('drivesnap')->put('backups/audit.enc', $archive);
    $backup = new Backup(['status' => 'completed', 'path' => 'backups/audit.enc', 'driver' => 'mysql', 'size' => strlen($archive), 'sha256' => hash('sha256', $archive)]);
    $backup->requestedBy()->associate($requester);
    $backup->save();

    $this->actingAs($downloader, 'admin')->get(route('drivesnap.download', $backup))->assertDownload();

    $this->assertDatabaseHas('audit_logs', ['action' => 'drivesnap.downloaded', 'actor_id' => $downloader->id]);
    $this->assertDatabaseMissing('audit_logs', ['action' => 'drivesnap.downloaded', 'actor_id' => $requester->id]);
});
