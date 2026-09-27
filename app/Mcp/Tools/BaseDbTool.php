<?php

namespace App\Mcp\Tools;

use App\Models\McpTool;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Server\Tool;

abstract class BaseDbTool extends Tool
{
    public function shouldRegister(): bool
    {
        $activeClasses = Cache::rememberForever('active_mcp_tools', function () {
            return McpTool::where('is_active', true)->pluck('class_name')->toArray();
        });

        return in_array(static::class, $activeClasses);
    }
}
