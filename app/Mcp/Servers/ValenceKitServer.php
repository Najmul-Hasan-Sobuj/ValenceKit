<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Valence Kit Server')]
#[Version('0.0.1')]
#[Instructions('Instructions describing how to use the server and its features.')]
class ValenceKitServer extends Server
{
    protected array $tools = [
        \App\Mcp\Tools\GetUserRoles::class,
    ];

    protected array $resources = [
        \App\Mcp\Resources\SystemLogs::class,
    ];

    protected array $prompts = [
        //
    ];
}
