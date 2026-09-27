<?php

namespace App\Services;

use App\Models\McpTool;
use Illuminate\Support\Facades\File;
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

            McpTool::updateOrCreate(
                ['class_name' => $className],
                [
                    'name' => $name,
                    'description' => $description,
                ]
            );
        }
    }

    private function getToolName(ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(Name::class);
        if (! empty($attributes)) {
            return $attributes[0]->newInstance()->value;
        }

        // Fallback to class name if no #[Name] attribute
        return $reflection->getShortName();
    }

    private function getToolDescription(ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(Description::class);
        if (! empty($attributes)) {
            return $attributes[0]->newInstance()->value;
        }

        return '';
    }
}
