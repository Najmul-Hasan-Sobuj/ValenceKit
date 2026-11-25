<?php

test('visual assets exist in public/static subdirectories with optimized file sizes', function () {
    $expectedImages = [
        'service/audit-premium-v1.jpg',
        'service/architecture-premium-v1.jpg',
        'service/apps-premium-v1.jpg',
        'service/cloud-premium-v1.jpg',
    ];

    foreach ($expectedImages as $image) {
        $path = public_path("static/{$image}");
        expect(file_exists($path))->toBeTrue("Expected {$image} to exist in public/static/");

        $sizeBytes = filesize($path);
        expect($sizeBytes)->toBeGreaterThan(1000)
            ->toBeLessThan(250 * 1024, "Image {$image} exceeds 250KB budget");
    }
});

test('public pages render accessible and performance-safe image tags with static paths', function () {
    $servicesComponent = file_get_contents(resource_path('js/pages/Services.vue'));

    $phaseImages = [
        '/static/service/audit-premium-v1.jpg',
        '/static/service/architecture-premium-v1.jpg',
        '/static/service/apps-premium-v1.jpg',
        '/static/service/cloud-premium-v1.jpg',
    ];

    foreach ($phaseImages as $img) {
        expect($servicesComponent)->toContain($img);
    }

    expect($servicesComponent)
        ->toContain('width="1200"')
        ->toContain('height="675"')
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"');
});

test('home and services routes return successful responses with visual elements', function () {
    $homeResponse = $this->get('/');
    $homeResponse->assertOk();

    $servicesResponse = $this->get('/services');
    $servicesResponse->assertOk();
});
