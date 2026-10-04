<?php

namespace App\Mcp\Tools;

use App\Models\McpTool;
use App\Services\McpAuthorizationService;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

abstract class BaseDbTool extends Tool
{
    protected function denyUnlessAllowed(Request $request, string $scope): ?Response
    {
        if (app(McpAuthorizationService::class)->allows($request, $scope)) {
            return null;
        }

        return Response::error('This MCP token does not have the required scope.');
    }

    public function shouldRegister(): bool
    {
        $activeClasses = Cache::rememberForever('active_mcp_tools', function () {
            return McpTool::where('is_active', true)->pluck('class_name')->toArray();
        });

        return in_array(static::class, $activeClasses);
    }
}
