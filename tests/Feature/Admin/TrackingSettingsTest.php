<?php

use App\Models\Admin;
use App\Models\TrackingSetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->assignRole('Super Admin');
});

test('unauthenticated visitors cannot access tracking settings', function () {
    $this->get(route('admin.tracking.settings.edit'))
        ->assertRedirect(route('admin.login'));
});

test('admin with settings.tracking.view permission can view tracking settings page', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.tracking.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/Tracking')
            ->has('settings')
            ->has('settings.clarity_enabled')
            ->has('settings.google_analytics_enabled')
            ->has('settings.google_tag_manager_enabled')
            ->has('settings.facebook_pixel_enabled')
            ->has('settings.custom_scripts_enabled')
            ->has('settings.exclude_admins')
        );
});

test('admin without settings.tracking.view permission is forbidden', function () {
    $staff = Admin::factory()->create();
    // No roles or permissions assigned

    $this->actingAs($staff, 'admin')
        ->get(route('admin.tracking.settings.edit'))
        ->assertForbidden();
});

test('tracking editors cannot change script settings without the super admin role', function () {
    $staff = Admin::factory()->create();
    $staff->givePermissionTo('settings.tracking.edit');

    $this->actingAs($staff, 'admin')->patch(route('admin.tracking.settings.update'), [
        'custom_scripts_enabled' => true,
        'custom_head_scripts' => '<script>alert(1)</script>',
    ])->assertForbidden();
});

test('admin can update tracking configuration and auto-extracts project ID from snippet', function () {
    $claritySnippet = <<<'HTML'
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "yyvng93thl");
</script>
HTML;

    $payload = [
        'clarity_enabled' => true,
        'clarity_project_id' => $claritySnippet,
        'google_analytics_enabled' => true,
        'google_analytics_id' => 'G-ABC123XYZ',
        'google_tag_manager_enabled' => true,
        'google_tag_manager_id' => 'GTM-TEST999',
        'facebook_pixel_enabled' => true,
        'facebook_pixel_id' => '987654321012345',
        'custom_scripts_enabled' => true,
        'custom_head_scripts' => '<script>console.log("custom head");</script>',
        'custom_body_scripts' => '<noscript><div>custom body</div></noscript>',
        'exclude_admins' => true,
    ];

    $this->actingAs($this->superAdmin, 'admin')
        ->patch(route('admin.tracking.settings.update'), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = TrackingSetting::instance();

    // Verify auto-extraction of Clarity ID
    expect($settings->clarity_enabled)->toBeTrue();
    expect($settings->clarity_project_id)->toBe('yyvng93thl');
    expect($settings->google_analytics_id)->toBe('G-ABC123XYZ');
    expect($settings->google_tag_manager_id)->toBe('GTM-TEST999');
    expect($settings->facebook_pixel_id)->toBe('987654321012345');
    expect($settings->custom_head_scripts)->toBe('<script>console.log("custom head");</script>');

    // Verify audit log record
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'settings.tracking.updated',
        'actor_id' => $this->superAdmin->id,
        'actor_type' => Admin::class,
    ]);
});

test('enabled tracking providers require an ID and reject malformed IDs', function (string $enabled, string $field, string $invalid) {
    $settings = TrackingSetting::instance();
    $payload = $settings->only($settings->getFillable());
    $before = $settings->fresh()->toArray();
    foreach (['', $invalid] as $value) {
        $this->actingAs($this->superAdmin, 'admin')->patchJson(route('admin.tracking.settings.update'), [
            ...$payload,
            $enabled => true,
            $field => $value,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect($settings->fresh()->toArray())->toBe($before);
})->with([
    ['clarity_enabled', 'clarity_project_id', 'invalid/project'],
    ['google_analytics_enabled', 'google_analytics_id', 'G-ABC-INVALID'],
    ['google_tag_manager_enabled', 'google_tag_manager_id', 'GTM-ABC-INVALID'],
    ['facebook_pixel_enabled', 'facebook_pixel_id', '123abc'],
]);

test('tracking settings normalize pasted IDs and provider installation snippets', function () {
    $settings = TrackingSetting::instance();
    $payload = $settings->only($settings->getFillable());
    $this->actingAs($this->superAdmin, 'admin')->patchJson(route('admin.tracking.settings.update'), [
        ...$payload,
        'google_analytics_enabled' => true,
        'google_analytics_id' => '<script async src="https://www.googletagmanager.com/gtag/js?id=g-abc123"></script>',
        'google_tag_manager_enabled' => true,
        'google_tag_manager_id' => '  gtm-abc123  ',
        'facebook_pixel_enabled' => true,
        'facebook_pixel_id' => "<script>fbq('init', '123456789');</script>",
    ])->assertRedirect();
    expect($settings->fresh())
        ->google_analytics_id->toBe('G-ABC123')
        ->google_tag_manager_id->toBe('GTM-ABC123')
        ->facebook_pixel_id->toBe('123456789');
});

test('view-only tracking administrators can inspect settings but cannot update them', function () {
    $viewer = Admin::factory()->create();
    $viewer->givePermissionTo('settings.tracking.view');
    $this->actingAs($viewer, 'admin')->get(route('admin.tracking.settings.edit'))->assertOk();
    $settings = TrackingSetting::instance();
    $this->patchJson(route('admin.tracking.settings.update'), [...$settings->only($settings->getFillable()), 'exclude_admins' => false])->assertForbidden();
    expect($settings->fresh()->exclude_admins)->toBeTrue();
});

test('web users cannot access admin tracking settings or update their configuration', function () {
    $this->actingAs(User::factory()->create(), 'web')->getJson(route('admin.tracking.settings.edit'))->assertUnauthorized();
    $this->patchJson(route('admin.tracking.settings.update'), ['custom_scripts_enabled' => true])->assertUnauthorized();
});

test('tracking ID extraction accepts single quotes whitespace and optional Meta init arguments', function () {
    $settings = TrackingSetting::instance();
    $this->actingAs($this->superAdmin, 'admin')->patchJson(route('admin.tracking.settings.update'), [
        ...$settings->only($settings->getFillable()),
        'clarity_enabled' => true,
        'clarity_project_id' => "(window, document, 'clarity', 'script', 'qatest01');",
        'google_analytics_enabled' => true,
        'google_analytics_id' => "gtag ('config', 'G-QATEST01');",
        'facebook_pixel_enabled' => true,
        'facebook_pixel_id' => "fbq( 'init', '123456789', { external_id: 'example' } );",
    ])->assertRedirect();
    expect($settings->fresh())->clarity_project_id->toBe('qatest01')->google_analytics_id->toBe('G-QATEST01')->facebook_pixel_id->toBe('123456789');
});
