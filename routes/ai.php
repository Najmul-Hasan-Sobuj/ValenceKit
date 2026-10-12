<?php

use App\Http\Middleware\McpTokenMiddleware;
use App\Mcp\Servers\ValenceKitServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/valencekit', ValenceKitServer::class)->middleware([
    'rate-limit:mcp',
    McpTokenMiddleware::class,
]);
