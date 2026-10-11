<?php

use App\Providers\AppServiceProvider;

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
