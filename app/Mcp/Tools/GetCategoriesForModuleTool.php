<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use Illuminate\Json\Schema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetCategoriesForModuleTool extends Tool
{
    protected string $description = 'List all categories for a given module (e.g. finance, product)';

    public function schema(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()->description('Module slug')->required(),
            'type' => $schema->string()->description('Filter by type: income, expense')->optional(),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'module' => 'required|string',
            'type' => 'nullable|string',
        ]);

        $categories = Category::forModule($request->get('module'))
            ->when($request->filled('type'), fn ($q) => $q->ofType($request->get('type')))
            ->latest()
            ->get();

        return Response::text($categories->toJson(JSON_PRETTY_PRINT));
    }
}
