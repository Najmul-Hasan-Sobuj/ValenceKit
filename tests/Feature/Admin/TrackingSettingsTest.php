<?php

use App\Models\Admin;
use App\Models\TrackingSetting;
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
