<?php

test('web responses include production security response headers', function () {
    $response = $this->get('/');

    $response->assertOk();

    // Verify baseline security headers
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), publickey-credentials-get=(self)');

    // Verify Content-Security-Policy
    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->not->toBeNull()
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain('nonce-')
        ->toContain("style-src 'self' 'unsafe-inline'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'");
});

test('https and production traffic enforces strict-transport-security', function () {
    // Insecure local request should not emit HSTS in non-production
    $insecureResponse = $this->get('http://localhost/');
    expect($insecureResponse->headers->has('Strict-Transport-Security'))->toBeFalse();

    // Secure HTTPS request emits HSTS
    $secureResponse = $this->get('https://localhost/');
    $secureResponse->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('csp nonce matches between response header and rendered script tag', function () {
    $response = $this->get('/');
    $response->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([a-zA-Z0-9_\-]+)'/", $csp, $matches);
    $nonce = $matches[1] ?? null;

    expect($nonce)->not->toBeNull();
    $response->assertSee('nonce="'.$nonce.'"', false);
});

test('connect-src allows websocket connections in local and testing environments for vite hmr', function () {
    $response = $this->get('/');
    $response->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("connect-src 'self'")
        ->toContain('ws:')
        ->toContain('wss:');
});

test('allows the Herd development script only outside production', function () {
    $hash = "'sha256-e9EdJBA/M3o5ruBrc1LqUrOz1yuPiJZlX/SG34Efiwg='";

    expect($this->get('/')->headers->get('Content-Security-Policy'))->toContain($hash);

    app()->instance('env', 'production');

    expect($this->get('/')->headers->get('Content-Security-Policy'))->not->toContain($hash);
});
