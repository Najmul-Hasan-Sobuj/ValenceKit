<?php

test('robots.txt returns plain text directives without blocking .well-known', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $content = $response->getContent();

    expect($content)->toContain('User-agent: *')
        ->and($content)->toContain('Allow: /')
        ->and($content)->toContain('Allow: /llms.txt')
        ->and($content)->toContain('Allow: /sitemap.xml')
        ->and($content)->toContain('Disallow: /admin')
        ->and($content)->not->toContain('Disallow: /.well-known/');
});

test('sitemap and llms endpoints return valid responses', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    $this->get('/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
});

test('public pages share dynamic supportEmail in inertia props', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('supportEmail', config('app.support_email', 'support@valencekit.com'))
        );
});
