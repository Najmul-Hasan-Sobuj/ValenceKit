<?php

use App\Models\Admin;
use App\Models\TrackingSetting;
use App\Services\TrackingService;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    TrackingSetting::flushCache();
});

test('public page renders Microsoft Clarity script when enabled', function () {
    $setting = TrackingSetting::instance();
    $setting->update([
        'clarity_enabled' => true,
        'clarity_project_id' => 'yyvng93thl',
        'exclude_admins' => true,
    ]);
    TrackingSetting::flushCache();

    $response = $this->get('/');
    $response->assertOk();

    $response->assertSee('https://www.clarity.ms/tag/', false);
    $response->assertSee('"clarity", "script", "yyvng93thl"', false);
    $response->assertSee('nonce=', false);
});

test('public page renders GA4, GTM, and Meta Pixel scripts with CSP nonces', function () {
    $setting = TrackingSetting::instance();
    $setting->update([
        'google_analytics_enabled' => true,
        'google_analytics_id' => 'G-ABC123XYZ',
        'google_tag_manager_enabled' => true,
        'google_tag_manager_id' => 'GTM-TEST999',
        'facebook_pixel_enabled' => true,
        'facebook_pixel_id' => '123456789012345',
        'custom_scripts_enabled' => true,
        'custom_head_scripts' => '<script>window.customTest = true;</script>',
        'custom_body_scripts' => '<noscript><div id="custom-body-pixel"></div></noscript>',
        'exclude_admins' => true,
    ]);
    TrackingSetting::flushCache();

    $response = $this->get('/');
    $response->assertOk();

    // GA4
    $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ', false);
    $response->assertSee("gtag('config', 'G-ABC123XYZ')", false);

    // GTM Head & Body
    $response->assertSee("googletagmanager.com/gtm.js?id='+i+dl", false);
    $response->assertSee('https://www.googletagmanager.com/ns.html?id=GTM-TEST999', false);

    // Meta Pixel
    $response->assertSee('https://connect.facebook.net/en_US/fbevents.js', false);
    $response->assertSee("fbq('init', '123456789012345')", false);

    // Custom Scripts
    $response->assertSee('window.customTest = true;', false);
    $response->assertSee('id="custom-body-pixel"', false);
});

test('tracking scripts are not rendered when providers are disabled', function () {
    $setting = TrackingSetting::instance();
    $setting->update([
        'clarity_enabled' => false,
        'clarity_project_id' => 'yyvng93thl',
        'google_analytics_enabled' => false,
        'google_tag_manager_enabled' => false,
        'facebook_pixel_enabled' => false,
        'custom_scripts_enabled' => false,
        'custom_head_scripts' => '<script>window.hiddenTest = true;</script>',
    ]);
    TrackingSetting::flushCache();

    $response = $this->get('/');
    $response->assertOk();

    $response->assertDontSee('https://www.clarity.ms/tag/');
    $response->assertDontSee('https://www.googletagmanager.com/gtag/js');
    $response->assertDontSee('https://connect.facebook.net/en_US/fbevents.js');
    $response->assertDontSee('window.hiddenTest = true;');
});

test('tracking scripts are suppressed on admin requests when exclude_admins is true', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');

    $setting = TrackingSetting::instance();
    $setting->update([
        'clarity_enabled' => true,
        'clarity_project_id' => 'yyvng93thl',
        'exclude_admins' => true,
    ]);
    TrackingSetting::flushCache();

    // Authenticated admin accessing admin area
    $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
    $response->assertOk();

    // Clarity should not be rendered
    $response->assertDontSee('https://www.clarity.ms/tag/');
    $response->assertDontSee('yyvng93thl');
});

test('tracking scripts respect dynamic admin alias configuration', function () {
    config(['admin.alias' => 'secret-manage']);

    $setting = TrackingSetting::instance();
    $setting->update([
        'clarity_enabled' => true,
        'clarity_project_id' => 'yyvng93thl',
        'exclude_admins' => true,
    ]);
    TrackingSetting::flushCache();

    $this->get('/secret-manage/login');
    expect(TrackingService::shouldTrack())->toBeFalse();
});
