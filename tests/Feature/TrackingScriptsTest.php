<?php

use App\Models\Admin;
use App\Models\TrackingSetting;
use App\Services\TrackingService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    TrackingSetting::flushCache();
    $this->withUnencryptedCookies(['tracking_consent' => json_encode(['version' => 1, 'analytics' => true, 'marketing' => true])]);
});

test('visitor tracking and fallback pixels obey strictly validated consent categories', function (string $cookie, bool $analytics, bool $marketing) {
    $setting = TrackingSetting::instance();
    $setting->update([
        'clarity_enabled' => true, 'clarity_project_id' => 'qatest01',
        'google_analytics_enabled' => true, 'google_analytics_id' => 'G-QATEST01',
        'google_tag_manager_enabled' => true, 'google_tag_manager_id' => 'GTM-QATEST01',
        'facebook_pixel_enabled' => true, 'facebook_pixel_id' => '123456789',
        'custom_scripts_enabled' => true, 'custom_head_scripts' => '<script>window.consentCustomHead = true;</script>',
        'custom_body_scripts' => '<img src="https://example.com/consent-test.gif">',
    ]);
    TrackingSetting::flushCache();
    $this->withUnencryptedCookies(['tracking_consent' => $cookie]);
    $response = $this->get('/')->assertOk();
    foreach (['https://www.clarity.ms/tag/', 'gtag/js?id=G-QATEST01'] as $script) {
        $analytics ? $response->assertSee($script, false) : $response->assertDontSee($script, false);
    }
    foreach (['https://connect.facebook.net/en_US/fbevents.js', 'https://www.facebook.com/tr?id=123456789'] as $script) {
        $marketing ? $response->assertSee($script, false) : $response->assertDontSee($script, false);
    }
    foreach (['gtm.js?id=', 'ns.html?id=GTM-QATEST01', 'window.consentCustomHead', 'https://example.com/consent-test.gif'] as $script) {
        ($analytics && $marketing) ? $response->assertSee($script, false) : $response->assertDontSee($script, false);
    }
})->with([
    'no preference' => ['', false, false],
    'malformed preference' => ['not-json', false, false],
    'invalid types' => ['{"version":1,"analytics":"true","marketing":true}', false, false],
    'outdated version' => ['{"version":0,"analytics":true,"marketing":true}', false, false],
    'rejected' => ['{"version":1,"analytics":false,"marketing":false}', false, false],
    'analytics only' => ['{"version":1,"analytics":true,"marketing":false}', true, false],
    'marketing only' => ['{"version":1,"analytics":false,"marketing":true}', false, true],
    'accepted all' => ['{"version":1,"analytics":true,"marketing":true}', true, true],
]);

test('Google consent defaults precede enabled measurement tags and Clarity receives consentv2', function () {
    $settings = TrackingSetting::instance();
    $settings->update(['google_analytics_enabled' => true, 'google_analytics_id' => 'G-QATEST01', 'clarity_enabled' => true, 'clarity_project_id' => 'qatest01']);
    TrackingSetting::flushCache();
    $response = $this->get('/')->assertOk();
    expect(strpos($response->getContent(), "gtag('consent', 'default'"))->toBeLessThan(strpos($response->getContent(), "gtag('config', 'G-QATEST01')"));
    $response->assertSee("clarity('consentv2', {analytics_Storage: 'granted', ad_Storage: 'granted'})", false);
});

test('signed-in administrators remain excluded on public pages despite full visitor consent', function () {
    $settings = TrackingSetting::instance();
    $settings->update(['clarity_enabled' => true, 'clarity_project_id' => 'qatest01', 'exclude_admins' => true]);
    TrackingSetting::flushCache();
    $admin = Admin::factory()->create();
    $response = $this->actingAs($admin, 'admin')->get('/')->assertOk();
    $response->assertDontSee('https://www.clarity.ms/tag/', false);
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('tracking.allowed', false)->where('tracking.available', true));
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

test('admin exclusion does not suppress public paths that merely share the admin prefix', function () {
    $settings = TrackingSetting::instance();
    $settings->update(['exclude_admins' => true]);
    TrackingSetting::flushCache();
    $this->get('/administrator-services');
    expect(TrackingService::shouldTrack())->toBeTrue();
});

test('GTM dynamically loaded script receives the request CSP nonce', function () {
    $settings = TrackingSetting::instance();
    $settings->update(['google_tag_manager_enabled' => true, 'google_tag_manager_id' => 'GTM-TEST999']);
    TrackingSetting::flushCache();
    $response = $this->get('/')->assertOk();
    $nonce = Vite::cspNonce();
    $response->assertSee("j.setAttribute('nonce','{$nonce}')", false);
});

test('production CSP permits regional Google Analytics collection endpoints', function () {
    app()->detectEnvironment(fn () => 'production');
    $response = $this->get('/')->assertOk();
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('https://*.google-analytics.com')
        ->toContain('https://*.analytics.google.com');
});

test('custom scripts replace stale nonces while preserving attributes and code', function () {
    $setting = TrackingSetting::instance();
    $setting->update([
        'custom_scripts_enabled' => true,
        'custom_head_scripts' => '<script nonce="old-head" data-nonce="keep" type="application/javascript">window.customNonceTest = true;</script>',
        'custom_body_scripts' => "<script nonce = 'old-body' src=\"/test-tracker.js\" defer></script><script NONCE=old-unquoted>window.bodyNonceTest = true;</script>",
    ]);
    TrackingSetting::flushCache();

    $response = $this->get('/')->assertOk();
    $nonce = Vite::cspNonce();
    $response->assertSee('<script nonce="'.$nonce.'" data-nonce="keep" type="application/javascript">window.customNonceTest = true;</script>', false);
    $response->assertSee('<script nonce="'.$nonce.'" src="/test-tracker.js" defer></script>', false);
    $response->assertSee('<script nonce="'.$nonce.'">window.bodyNonceTest = true;</script>', false);
    $response->assertDontSee('old-head')->assertDontSee('old-body')->assertDontSee('old-unquoted');
});

test('nonce injection preserves quoted greater-than attributes and script-like JavaScript strings', function () {
    $settings = TrackingSetting::instance();
    $code = <<<'HTML'
<script data-compare="2 > 1" data-example="text nonce='keep'" nonce="old">window.template = '<script data-example="inner">';</script>
HTML;
    $settings->update(['custom_scripts_enabled' => true, 'custom_head_scripts' => $code]);
    TrackingSetting::flushCache();
    $response = $this->get('/')->assertOk();
    $nonce = Vite::cspNonce();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    $script = (new DOMXPath($document))->query('//script[@data-compare]')->item(0);
    expect($script)->not->toBeNull();
    expect($script->getAttribute('nonce'))->toBe($nonce);
    expect($script->getAttribute('data-compare'))->toBe('2 > 1');
    expect($script->getAttribute('data-example'))->toBe("text nonce='keep'");
    expect($script->textContent)->toBe("window.template = '<script data-example=\"inner\">';");
});
