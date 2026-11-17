<?php

use App\Jobs\BuildDataExport;
use App\Models\Admin;
use App\Models\DataExport;
use App\Models\Lead;
use App\Services\ExportService;
use App\Services\FinanceService;
use App\Services\HrService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

it('queues private exports and rechecks ownership permissions and expiry at download', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Bus::fake();
    Storage::fake('local');
    $actor = Admin::factory()->create(['is_active' => true]);
    $other = Admin::factory()->create(['is_active' => true]);
    $this->actingAs($actor, 'admin')->postJson(route('admin.exports.store'), ['module' => 'leads'])->assertForbidden();
    $actor->givePermissionTo('leads.export');
    $other->givePermissionTo('leads.export');
    Lead::factory()->create(['name' => '=HYPERLINK("invalid")']);
    $this->post(route('admin.exports.store'), ['module' => 'leads'])->assertSessionHasNoErrors();
    Bus::assertDispatched(BuildDataExport::class);
    $export = DataExport::firstOrFail();
    (new BuildDataExport($export->id))->handle(app(ExportService::class), app(FinanceService::class), app(HrService::class));
    $export->refresh();
    expect($export->status)->toBe('ready')->and(Storage::disk('local')->get($export->path))->toContain("'=HYPERLINK");
    $this->get(route('admin.exports.download', $export))->assertDownload();
    $this->actingAs($other, 'admin')->get(route('admin.exports.download', $export))->assertForbidden();
    $actor->revokePermissionTo('leads.export');
    $this->actingAs($actor, 'admin')->get(route('admin.exports.download', $export))->assertForbidden();
    $actor->givePermissionTo('leads.export');
    $this->travel(25)->hours();
    $this->get(route('admin.exports.download', $export))->assertNotFound();
    $this->artisan('operations:prune', ['--apply' => true])->assertSuccessful();
    Storage::disk('local')->assertMissing($export->path);
});
