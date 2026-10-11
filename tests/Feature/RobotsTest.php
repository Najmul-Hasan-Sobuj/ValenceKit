<?php

test('robots file publishes crawler rules and the sitemap', function () {
    $response = $this->get(route('robots'));
    $baseUrl = rtrim((string) config('app.url'), '/');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('User-agent: *', false)
        ->assertSee('Allow: /', false)
        ->assertSee('Allow: /llms.txt', false)
        ->assertSee('Allow: /sitemap.xml', false)
        ->assertSee('Disallow: /admin', false)
        ->assertSee('Disallow: /api/', false)
        ->assertSee('Disallow: /settings', false)
        ->assertSee("Sitemap: {$baseUrl}/sitemap.xml", false);
});

test('private routes remain protected from guests', function () {
    $this->get('/admin/dashboard')->assertRedirect();
    $this->get('/settings/profile')->assertRedirect();
    $this->getJson('/api/user')->assertUnauthorized();
});
