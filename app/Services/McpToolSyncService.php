<?php

namespace App\Services;

use App\Models\McpTool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;

class McpToolSyncService
{
    public function sync(): void
    {
        $toolsPath = app_path('Mcp/Tools');

        if (! File::exists($toolsPath)) {
            return;
        }

        $files = File::allFiles($toolsPath);

        foreach ($files as $file) {
            $className = 'App\\Mcp\\Tools\\'.$file->getFilenameWithoutExtension();

            if (! class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            // Check if it's instantiable and extends the base Tool class
            if (! $reflection->isInstantiable() || ! $reflection->isSubclassOf(Tool::class)) {
                continue;
            }

            $name = $this->getToolName($reflection);
            $description = $this->getToolDescription($reflection);

            $tool = McpTool::firstOrNew(['class_name' => $className]);
            $tool->fill([
                'name' => $name,
                'description' => $description,
                'slug' => Str::kebab($reflection->getShortName()),
                'version' => '1.0.0',
                'provider' => config('app.name'),
                'transport' => 'http',
                'required_scope' => $this->requiredScope($reflection->getShortName()),
                'health_status' => 'healthy',
                'last_synced_at' => now(),
            ]);

            $tool->save();
        }
    }

    /**
     * @param  ReflectionClass<Tool>  $reflection
     */
    private function getToolName(ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(Name::class);
        if (! empty($attributes)) {
            return $attributes[0]->newInstance()->value;
        }

        // Fallback to class name if no #[Name] attribute
        return $reflection->getShortName();
    }

    /**
     * @param  ReflectionClass<Tool>  $reflection
     */
    private function getToolDescription(ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(Description::class);
        if (! empty($attributes)) {
            return $attributes[0]->newInstance()->value;
        }

        return '';
    }

    private function requiredScope(string $shortName): string
    {
        return match ($shortName) {
            'RecordTransactionTool' => 'mcp:finance.write',
            'GetFinancialSummaryTool' => 'mcp:finance.read',
            'GetUserRoles' => 'mcp:users.read',
            default => 'mcp:tools.read',
        };
    }
}
