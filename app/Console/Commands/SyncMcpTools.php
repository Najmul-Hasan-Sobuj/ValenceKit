<?php

namespace App\Console\Commands;

use App\Services\McpToolSyncService;
use Illuminate\Console\Command;

class SyncMcpTools extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:sync-tools';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize MCP tools with the database';

    /**
     * Execute the console command.
     */
    public function handle(McpToolSyncService $syncService): int
    {
        $this->info('Starting MCP tools synchronization...');

        $syncService->sync();

        $this->info('MCP tools synchronized successfully.');

        return self::SUCCESS;
    }
}
