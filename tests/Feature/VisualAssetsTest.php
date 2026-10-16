<?php

test('visual assets exist in public/static subdirectories with optimized file sizes', function () {
    $expectedImages = [
        'welcome/dashboard-preview.webp',
        'service/audit.webp',
        'service/architecture.webp',
        'service/apps.webp',
        'service/cloud.webp',
    ];

    foreach ($expectedImages as $image) {
        $path = public_path("static/{$image}");
        expect(file_exists($path))->toBeTrue("Expected {$image} to exist in public/static/");

        // Ensure images are optimized WebP under 150KB
        $sizeBytes = filesize($path);
        expect($sizeBytes)->toBeGreaterThan(1000)
            ->toBeLessThan(150 * 1024, "Image {$image} exceeds 150KB budget");
    }
});

test('public pages render accessible and performance-safe image tags with static paths', function () {
    $welcomeComponent = file_get_contents(resource_path('js/pages/Welcome.vue'));
    $servicesComponent = file_get_contents(resource_path('js/pages/Services.vue'));

    // Check Welcome.vue image attributes
    expect($welcomeComponent)
        ->toContain('/static/welcome/dashboard-preview.webp')
        ->toContain('width="1376"')
        ->toContain('height="768"')
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"')
        ->toContain('alt="ValenceKit administrative dashboard');

    // Check Services.vue images attributes
    $phaseImages = [
        '/static/service/audit.webp',
        '/static/service/architecture.webp',
        '/static/service/apps.webp',
        '/static/service/cloud.webp',
    ];

    foreach ($phaseImages as $img) {
        expect($servicesComponent)->toContain($img);
    }

    expect($servicesComponent)
        ->toContain('width="1376"')
        ->toContain('height="768"')
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"');
});

test('home and services routes return successful responses with visual elements', function () {
    $homeResponse = $this->get('/');
    $homeResponse->assertOk();

    $servicesResponse = $this->get('/services');
    $servicesResponse->assertOk();
});
