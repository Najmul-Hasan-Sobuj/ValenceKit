<?php

test('visual assets exist in public/images with optimized file sizes', function () {
    $expectedImages = [
        'dashboard-preview.webp',
        'audit-service.webp',
        'architecture-service.webp',
        'apps-service.webp',
        'cloud-service.webp',
    ];

    foreach ($expectedImages as $image) {
        $path = public_path("images/{$image}");
        expect(file_exists($path))->toBeTrue("Expected {$image} to exist in public/images/");

        // Ensure images are optimized WebP under 150KB
        $sizeBytes = filesize($path);
        expect($sizeBytes)->toBeGreaterThan(1000)
            ->toBeLessThan(150 * 1024, "Image {$image} exceeds 150KB budget");
    }
});

test('public pages render accessible and performance-safe image tags', function () {
    $welcomeComponent = file_get_contents(resource_path('js/pages/Welcome.vue'));
    $servicesComponent = file_get_contents(resource_path('js/pages/Services.vue'));

    // Check Welcome.vue image attributes
    expect($welcomeComponent)
        ->toContain('/images/dashboard-preview.webp')
        ->toContain('width="1200"')
        ->toContain('height="675"')
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"')
        ->toContain('alt="ValenceKit administrative dashboard');

    // Check Services.vue images attributes
    $phaseImages = [
        '/images/audit-service.webp',
        '/images/architecture-service.webp',
        '/images/apps-service.webp',
        '/images/cloud-service.webp',
    ];

    foreach ($phaseImages as $img) {
        expect($servicesComponent)->toContain($img);
    }

    expect($servicesComponent)
        ->toContain('width="960"')
        ->toContain('height="540"')
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"');
});

test('home and services routes return successful responses with visual elements', function () {
    $homeResponse = $this->get('/');
    $homeResponse->assertOk();

    $servicesResponse = $this->get('/services');
    $servicesResponse->assertOk();
});
