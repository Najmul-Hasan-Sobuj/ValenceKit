<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\SystemLogs;
use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\GetUserRoles;
use App\Mcp\Tools\RecordTransactionTool;
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
        GetUserRoles::class,
        RecordTransactionTool::class,
        GetFinancialSummaryTool::class,
    ];

    protected array $resources = [
        SystemLogs::class,
    ];

    protected array $prompts = [
        //
    ];
}
