<?php

use App\Http\Middleware\McpTokenMiddleware;
use App\Mcp\Servers\ValenceKitServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/valencekit', ValenceKitServer::class)->middleware(McpTokenMiddleware::class);
