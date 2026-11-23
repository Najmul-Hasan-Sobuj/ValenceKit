<?php

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Media;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('local');
});

function mediaAdmin(array $permissions = ['media.view', 'media.create', 'media.edit', 'media.delete']): Admin
{
    $admin = Admin::factory()->create();
    $admin->givePermissionTo($permissions);

    return $admin;
}

it('requires admin authentication and granular permissions for all media entry points', function () {
    $media = Media::factory()->create();
    $this->get(route('admin.media.index'))->assertRedirect(route('admin.login'));
    $this->actingAs(User::factory()->create(), 'web')->getJson(route('admin.media.file', $media))->assertUnauthorized();
    $this->actingAs(Admin::factory()->create(), 'admin');
    $this->getJson(route('admin.media.index'))->assertForbidden();
    $this->getJson(route('admin.media.file', $media))->assertForbidden();
    $this->postJson(route('admin.media.store'))->assertForbidden();
    $this->patchJson(route('admin.media.update', $media))->assertForbidden();
    $this->deleteJson(route('admin.media.destroy', $media))->assertForbidden();
    $this->assertModelExists($media);
});

it('uploads multiple private images and filters the library by folder search and usage', function () {
    $admin = mediaAdmin();
    $this->actingAs($admin, 'admin')->post(route('admin.media.store'), [
        'files' => [UploadedFile::fake()->image('catalog.jpg', 800, 600), UploadedFile::fake()->image('stock.png')],
        'collection' => 'gallery', 'folder' => 'Products',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseCount('media', 2);
    $image = Media::where('name', 'catalog')->firstOrFail();
    Storage::disk('local')->assertExists($image->path);
    expect($image->mediable_type)->toBeNull();
    expect($image->uploaded_by)->toBe($admin->id);
    $this->get(route('admin.media.file', $image))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('admin.media.index', ['folder' => 'Products', 'search' => 'catalog', 'usage' => 'unattached']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('media.data', 1)->where('media.data.0.name', 'catalog')->where('stats.total', 2)->missing('media.data.0.path')->missing('media.data.0.disk'));
    $this->get(route('admin.media.index', ['search' => 'not-found']))->assertInertia(fn (Assert $page) => $page->has('media.data', 0));
    $this->assertDatabaseHas('audit_logs', ['action' => 'media.created', 'subject_id' => $image->id]);
});

it('rejects unsafe images oversized uploads unknown owners and invalid single image batches', function () {
    $this->actingAs(mediaAdmin(), 'admin');
    $base = ['collection' => 'gallery', 'folder' => 'General'];
    foreach ([UploadedFile::fake()->create('payload.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('large.jpg')->size(5121), UploadedFile::fake()->create('script.jpg', 1, 'text/plain')] as $file) {
        $this->postJson(route('admin.media.store'), [...$base, 'files' => [$file]])->assertUnprocessable()->assertJsonValidationErrors('files.0');
    }
    $this->postJson(route('admin.media.store'), [...$base, 'files' => [UploadedFile::fake()->image('safe.jpg')], 'owner_type' => Admin::class, 'owner_id' => 1])->assertUnprocessable()->assertJsonValidationErrors('owner_type');
    $this->postJson(route('admin.media.store'), [...$base, 'files' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')], 'collection' => 'avatar'])->assertUnprocessable()->assertJsonValidationErrors('files');
    $this->assertDatabaseCount('media', 0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('edits metadata replaces files and deletes the file and audit records correctly', function () {
    $this->actingAs(mediaAdmin(), 'admin');
    $this->post(route('admin.media.store'), ['files' => [UploadedFile::fake()->image('original.jpg')], 'collection' => 'gallery', 'folder' => 'General']);
    $media = Media::firstOrFail();
    $oldPath = $media->path;
    $oldUrl = $this->get(route('admin.media.index'))->viewData('page')['props']['media']['data'][0]['url'];
    $this->post(route('admin.media.update', $media), ['_method' => 'PATCH', 'name' => 'Product cover', 'alt' => 'A product on a desk', 'folder' => 'Products', 'file' => UploadedFile::fake()->image('replacement.png', 320, 240)])
        ->assertRedirect()->assertSessionHasNoErrors();
    $media->refresh();
    expect($media->name)->toBe('Product cover');
    expect($media->width)->toBe(320);
    Storage::disk('local')->assertExists($media->path);
    Storage::disk('local')->assertMissing($oldPath);
    $this->get(route('admin.media.index'))->assertInertia(fn (Assert $page) => $page->where('media.data.0.url', fn (string $url): bool => $url !== $oldUrl));
    $this->assertDatabaseHas('audit_logs', ['action' => 'media.updated', 'subject_id' => $media->id]);
    $this->delete(route('admin.media.destroy', $media))->assertRedirect();
    $this->assertModelMissing($media);
    Storage::disk('local')->assertMissing($media->path);
    $this->assertDatabaseHas('audit_logs', ['action' => 'media.deleted', 'subject_id' => $media->id]);
    expect($media->path)->not->toBe($oldPath);
});

it('paginates images and keeps search and folder filters in page links', function () {
    Media::factory()->count(25)->create(['folder' => 'Products', 'name' => 'Product image']);
    $this->actingAs(mediaAdmin(['media.view']), 'admin')->get(route('admin.media.index', ['folder' => 'Products', 'search' => 'Product', 'page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('media.data', 1)->where('media.meta.total', 25)->where('media.meta.from', 25)->where('filters.folder', 'Products')->where('filters.search', 'Product'));
    $last = Media::oldest('id')->firstOrFail();
    $last->delete();
    $this->get(route('admin.media.index', ['folder' => 'Products', 'search' => 'Product', 'page' => 2]))
        ->assertRedirect(route('admin.media.index', ['folder' => 'Products', 'search' => 'Product', 'page' => 1]));
});

it('rejects invalid edits without changing the stored file or details', function () {
    $media = Media::factory()->create();
    $this->actingAs(mediaAdmin(), 'admin')->patchJson(route('admin.media.update', $media), ['name' => '', 'folder' => 'General'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    expect($media->fresh()->name)->toBe($media->name);
    $this->postJson(route('admin.media.store'), ['files' => UploadedFile::fake()->image('one.jpg'), 'collection' => 'avatar', 'folder' => 'General'])
        ->assertUnprocessable()->assertJsonValidationErrors('files');
    $this->assertDatabaseCount('media', 1);
});

it('scopes employee photos to the linked employee and direct reports', function () {
    $actor = mediaAdmin(['media.view', 'hr.team']);
    $manager = Employee::factory()->create(['admin_id' => $actor->id]);
    $report = Employee::factory()->create(['manager_id' => $manager->id]);
    $outsider = Employee::factory()->create();
    foreach ([$manager, $report, $outsider] as $employee) {
        $employee->media()->create(Media::factory()->make()->getAttributes());
    }
    $this->actingAs($actor, 'admin')->get(route('admin.media.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('media.data', 2)->where('stats.total', 2));
    $this->get(route('admin.media.file', $outsider->media()->firstOrFail()))->assertNotFound();
});

it('uses polymorphic employee photos and enforces the single photo and HR scope', function () {
    $employee = Employee::factory()->create();
    $admin = mediaAdmin(['media.view', 'media.create', 'media.edit', 'media.delete', 'employees.view', 'employees.edit']);
    $this->actingAs($admin, 'admin');
    $payload = ['owner_type' => 'employee', 'owner_id' => $employee->id, 'collection' => 'avatar', 'folder' => 'Employees'];
    $this->post(route('admin.media.store'), [...$payload, 'files' => [UploadedFile::fake()->image('first.jpg')]])->assertRedirect()->assertSessionHasNoErrors();
    $first = $employee->media()->firstOrFail();
    expect($first->mediable->is($employee))->toBeTrue();
    $this->post(route('admin.media.store'), [...$payload, 'files' => [UploadedFile::fake()->image('second.jpg')]])->assertRedirect()->assertSessionHasNoErrors();
    expect($employee->media()->count())->toBe(1);
    $this->assertModelMissing($first);
    $photo = $employee->media()->firstOrFail();
    $this->get(route('admin.hr.index', ['section' => 'employees']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('employees.data.0.media.0.id', $photo->id));
    $this->actingAs(mediaAdmin(), 'admin')->get(route('admin.media.file', $photo))->assertNotFound();
    $this->patchJson(route('admin.media.update', $photo), ['name' => 'Forbidden', 'folder' => 'General'])->assertNotFound();
    $this->deleteJson(route('admin.media.destroy', $photo))->assertNotFound();
    $this->postJson(route('admin.media.store'), [...$payload, 'files' => [UploadedFile::fake()->image('blocked.jpg')]])->assertNotFound();
    $this->assertModelExists($photo);
});

it('does not grant employee editors media access or HR viewers image mutations', function () {
    $employee = Employee::factory()->create();
    $media = $employee->media()->create(Media::factory()->make()->getAttributes());
    $viewer = mediaAdmin(['employees.view', 'media.view', 'media.edit', 'media.delete', 'media.create']);
    $this->actingAs($viewer, 'admin')->patchJson(route('admin.media.update', $media), ['name' => 'Blocked', 'folder' => 'General'])->assertForbidden();
    $this->deleteJson(route('admin.media.destroy', $media))->assertForbidden();
    $this->actingAs(mediaAdmin(['employees.view', 'employees.edit']), 'admin')->postJson(route('admin.media.store'))->assertForbidden();
    $this->assertModelExists($media);
});

it('requires edit permission before replacing an existing profile photo through upload', function () {
    $employee = Employee::factory()->create();
    $actor = mediaAdmin(['employees.view', 'employees.edit', 'media.create']);
    $payload = ['owner_type' => 'employee', 'owner_id' => $employee->id, 'collection' => 'avatar', 'folder' => 'Employees'];
    $this->actingAs($actor, 'admin')->post(route('admin.media.store'), [...$payload, 'files' => [UploadedFile::fake()->image('first.jpg')]])->assertRedirect();
    $media = $employee->media()->firstOrFail();
    $this->postJson(route('admin.media.store'), [...$payload, 'files' => [UploadedFile::fake()->image('forbidden.jpg')]])->assertForbidden();
    $this->assertModelExists($media);
    expect($employee->media()->count())->toBe(1);
    Storage::disk('local')->assertExists($media->path);
});

it('blocks all media endpoints while disabled and restores access without changing permissions', function () {
    $actor = mediaAdmin();
    $permissions = $actor->getAllPermissions()->pluck('name')->sort()->values()->all();
    $media = Media::factory()->create();
    Module::create(['key' => 'media', 'enabled' => false]);
    $this->actingAs($actor, 'admin');
    $this->get(route('admin.media.index'))->assertNotFound();
    $this->get(route('admin.media.file', $media))->assertNotFound();
    $this->postJson(route('admin.media.store'))->assertNotFound();
    $this->patchJson(route('admin.media.update', $media))->assertNotFound();
    $this->deleteJson(route('admin.media.destroy', $media))->assertNotFound();
    Module::where('key', 'media')->update(['enabled' => true]);
    $this->get(route('admin.media.index'))->assertOk();
    expect($actor->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe($permissions);
});

it('blocks employee media if the people module is disabled', function () {
    $employee = Employee::factory()->create();
    $media = $employee->media()->create(Media::factory()->make()->getAttributes());
    Module::create(['key' => 'people', 'enabled' => false]);
    $this->actingAs(mediaAdmin(['media.view', 'media.edit', 'media.delete', 'media.create', 'employees.view', 'employees.edit']), 'admin');
    $this->get(route('admin.media.file', $media))->assertNotFound();
    $this->deleteJson(route('admin.media.destroy', $media))->assertNotFound();
    $this->get(route('admin.media.index'))->assertInertia(fn (Assert $page) => $page->has('media.data', 0));
});
