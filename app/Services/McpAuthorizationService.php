<?php

namespace App\Services;

use App\Models\Admin;
use Laravel\Mcp\Request;

class McpAuthorizationService
{
    /**
     * Determine whether the authenticated MCP admin may use a scope.
     */
    public function allows(Request $request, string $scope): bool
    {
        if (config('services.mcp.default_token') && request()->bearerToken() === config('services.mcp.default_token')) {
            return true;
        }

        $admin = $request->user('mcp') ?? $request->user('sanctum');

        if (! $admin instanceof Admin) {
            return false;
        }

        $token = $admin->currentAccessToken();

        if ($token->can($scope)) {
            return true;
        }

        return $token->can('mcp:use') || $token->can('mcp:access')
            ? $admin->can($this->permissionFor($scope))
            : false;
    }

    private function permissionFor(string $scope): string
    {
        return match ($scope) {
            'mcp:finance.read' => 'finance.view',
            'mcp:finance.write' => 'finance.create',
            'mcp:users.read' => 'users.view',
            default => 'settings.mcp.view',
        };
    }
}
