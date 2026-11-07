<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustHosts;

it('rejects unsafe production configuration at boot', function () {
    config([
        'app.debug' => true,
        'app.url' => 'https://valencekit.test',
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
    ]);
    app()->instance('env', 'production');

    expect(fn () => (new AppServiceProvider(app()))->boot())
        ->toThrow(LogicException::class, 'APP_DEBUG=false');
});

it('trusts only the configured application host', function () {
    config(['app.url' => 'https://app.example.com']);
    $patterns = app(TrustHosts::class)->hosts();

    expect($patterns)->toHaveCount(1)
        ->and(preg_match('/'.$patterns[0].'/', 'app.example.com'))->toBe(1)
        ->and(preg_match('/'.$patterns[0].'/', 'attacker.example.com'))->toBe(0)
        ->and(preg_match('/'.$patterns[0].'/', 'appXexampleXcom'))->toBe(0);
});
