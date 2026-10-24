<?php

namespace App\Mcp\Tools;

use App\Models\Admin;
use App\Models\McpTool;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

abstract class BaseDbTool extends Tool
{
    /**
     * Determine whether the authenticated MCP admin has the required scope.
     */
    protected function allows(Request $request, string $scope): bool
    {
        $admin = rescue(static fn () => $request->user('mcp'), null, false) ?? $request->user('sanctum');

        if (! $admin instanceof Admin) {
            return false;
        }

        $token = $admin->currentAccessToken();

        if (! $token) {
            return false;
        }

        if ($token->can($scope)) {
            return true;
        }

        return $token->can('mcp:use') || $token->can('mcp:access')
            ? $admin->can($this->permissionFor($scope))
            : false;
    }

    protected function denyUnlessAllowed(Request $request, string $scope): ?Response
    {
        if ($this->allows($request, $scope)) {
            return null;
        }

        return Response::error('This MCP token does not have the required scope.');
    }

    protected function permissionFor(string $scope): string
    {
        return match ($scope) {
            'mcp:finance.read' => 'finance.view',
            'mcp:finance.write' => 'finance.create',
            'mcp:users.read' => 'users.view',
            default => 'settings.mcp.view',
        };
    }

    public function shouldRegister(): bool
    {
        $activeClasses = Cache::rememberForever('active_mcp_tools', function () {
            return McpTool::where('is_active', true)->pluck('class_name')->toArray();
        });

        return in_array(static::class, $activeClasses);
    }
}
