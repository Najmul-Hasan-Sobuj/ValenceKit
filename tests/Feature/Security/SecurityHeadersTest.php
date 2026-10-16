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
    $insecureResponse = $this->get('/');
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
