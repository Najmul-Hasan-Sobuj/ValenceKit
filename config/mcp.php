<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | These domains are the domains that OAuth clients are permitted to use
    | for redirect URIs. Each domain should be specified with its scheme
    | and host. Domains not in this list will raise validation errors.
    |
    | Add trusted origins as comma-separated values in MCP_REDIRECT_DOMAINS.
    | The application URL is always included for the first-party client.
    |
    */

    'redirect_domains' => [
        ...array_values(array_filter(array_map(
            trim(...),
            [(string) env('APP_URL', ''), ...explode(',', (string) env('MCP_REDIRECT_DOMAINS', ''))],
        ), static fn (string $domain): bool => $domain !== '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Native desktop OAuth clients like Cursor and VS Code use private-use URI
    | schemes (RFC 8252) for redirect callbacks instead of standard schemes
    | like HTTPS. Here, you may list which custom schemes you will allow.
    |
    */

    'custom_schemes' => [
        // 'claude',
        // 'cursor',
        // 'vscode',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Server
    |--------------------------------------------------------------------------
    |
    | Here you may configure the OAuth authorization server issuer identifier
    | per RFC 8414. This value appears in your protected resource and auth
    | server metadata endpoints. When null, this defaults to `url('/')`.
    |
    */

    'authorization_server' => null,

    /*
    |--------------------------------------------------------------------------
    | Tool Search
    |--------------------------------------------------------------------------
    |
    | Here you may configure the limits enforced during tool search. The max
    | number of tool calls limits how many tools search requests can call
    | while the maximum output bytes value will limit the result sizes.
    |
    */

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

    'scopes' => [
        'mcp:tools.read' => 'View enabled MCP tools',
        'mcp:finance.read' => 'Read finance summaries and categories',
        'mcp:finance.write' => 'Create finance transactions',
        'mcp:users.read' => 'Read admin roles and permissions',
    ],

];
