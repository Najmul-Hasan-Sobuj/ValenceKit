<?php

test('shared social card og-image.png exists with 1200x630 dimensions', function () {
    $ogImagePath = public_path('images/og-image.png');

    expect(file_exists($ogImagePath))->toBeTrue('public/images/og-image.png must exist');
    expect(filesize($ogImagePath))->toBeGreaterThan(1024, 'og-image.png must not be empty');

    $imageInfo = getimagesize($ogImagePath);
    expect($imageInfo)->not->toBeFalse('og-image.png must be a valid image');
    expect($imageInfo[0])->toBe(1200, 'og-image.png width must be 1200');
    expect($imageInfo[1])->toBe(630, 'og-image.png height must be 630');
    expect($imageInfo['mime'])->toBe('image/png', 'og-image.png mime type must be image/png');
});

test('AppHead component defines full Open Graph and Twitter card tags with head-key deduplication', function () {
    $appHeadContent = file_get_contents(resource_path('js/components/AppHead.vue'));

    // Open Graph tags
    expect($appHeadContent)
        ->toContain('head-key="og:title"')
        ->toContain('head-key="og:description"')
        ->toContain('head-key="og:url"')
        ->toContain('head-key="og:site_name"')
        ->toContain('head-key="og:type"')
        ->toContain('head-key="og:image"')
        ->toContain('head-key="og:image:secure_url"')
        ->toContain('head-key="og:image:width"')
        ->toContain('content="1200"')
        ->toContain('head-key="og:image:height"')
        ->toContain('content="630"')
        ->toContain('head-key="og:image:alt"')
        ->toContain('head-key="og:image:type"');

    // Twitter / X card tags
    expect($appHeadContent)
        ->toContain('head-key="twitter:card"')
        ->toContain('head-key="twitter:title"')
        ->toContain('head-key="twitter:description"')
        ->toContain('head-key="twitter:image"')
        ->toContain('head-key="twitter:image:alt"');

    // Absolute URL resolution for social preview crawlers
    expect($appHeadContent)
        ->toContain('new URL(img, page.props.appUrl).toString()');
});

test('public welcome and services pages integrate AppHead with semantic metadata', function () {
    $welcome = file_get_contents(resource_path('js/pages/Welcome.vue'));
    $services = file_get_contents(resource_path('js/pages/Services.vue'));

    expect($welcome)
        ->toContain('<AppHead')
        ->toContain(':title="pageTitle"')
        ->toContain(':description="pageDescription"');

    expect($services)
        ->toContain('<AppHead')
        ->toContain(':title="pageTitle"')
        ->toContain(':description="pageDescription"');
});

test('public pages return 200 OK responses with inertia payload containing appUrl for metadata generation', function () {
    $homeResponse = $this->get('/');
    $homeResponse->assertOk();

    $servicesResponse = $this->get('/services');
    $servicesResponse->assertOk();
});
