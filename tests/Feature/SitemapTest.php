<?php

test('sitemap lists public indexable pages', function () {
    $response = $this->get(route('sitemap'));
    $baseUrl = rtrim((string) config('app.url'), '/');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
        ->assertSee("<loc>{$baseUrl}/</loc>", false)
        ->assertSee("<loc>{$baseUrl}/services</loc>", false)
        ->assertSee('<lastmod>', false);
});
