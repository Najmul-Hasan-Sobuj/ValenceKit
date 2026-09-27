<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Resource;

#[Description('Retrieve recent application system error and debug logs.')]
class SystemLogs extends Resource
{
    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        $logFile = storage_path('logs/laravel.log');

        if (! file_exists($logFile)) {
            return Response::text('No system log file found.');
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $recent = array_slice($lines, -100);

        return Response::text(implode("\n", $recent));
    }
}
