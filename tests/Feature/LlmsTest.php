<?php

test('llms file describes public ValenceKit pages', function () {
    $response = $this->get(route('llms'));
    $baseUrl = rtrim((string) config('app.url'), '/');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSee('# ValenceKit', false)
        ->assertSee('Laravel 13 and Vue.js 3', false)
        ->assertSee("[Homepage]({$baseUrl}/)", false)
        ->assertSee("[Services]({$baseUrl}/services)", false)
        ->assertSee('https://github.com/Najmul-Hasan-Sobuj/ValenceKit', false)
        ->assertSee('SSR guide', false)
        ->assertDontSee('Disallow: /admin', false)
        ->assertDontSee('Disallow: /api', false)
        ->assertDontSee('Disallow: /settings', false);
});
