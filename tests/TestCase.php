<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys --force');
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function createPersonalAccessClient(string $provider = 'admins'): Client
    {
        $clientRepo = app(ClientRepository::class);

        try {
            return $clientRepo->personalAccessClient($provider);
        } catch (\Throwable) {
            return $clientRepo->createPersonalAccessGrantClient(
                "ValenceKit Test {$provider} Personal Access Client",
                $provider,
            );
        }
    }
}
