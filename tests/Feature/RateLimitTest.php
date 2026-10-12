<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

it('uses a stricter rate limit for MCP write operations', function () {
    $readRequest = Request::create('/mcp/valencekit', 'POST');
    $writeRequest = Request::create('/mcp/valencekit', 'POST', [
        'method' => 'tools/call',
        'params' => ['name' => 'record-transaction-tool'],
    ]);

    $readLimit = RateLimiter::limiter('mcp-read')($readRequest);
    $writeLimit = RateLimiter::limiter('mcp-write')($writeRequest);

    expect($writeLimit->maxAttempts)->toBeLessThan($readLimit->maxAttempts);
});

it('returns a predictable response when the API limit is exceeded', function () {
    RateLimiter::clear(md5('apiapi:'.hash('sha256', '').':127.0.0.1'));

    foreach (range(1, 60) as $attempt) {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    $this->getJson('/api/user')
        ->assertStatus(429)
        ->assertJson(['message' => 'Too Many Requests.']);
});
