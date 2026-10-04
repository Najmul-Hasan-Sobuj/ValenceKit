<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetCategoriesForModuleTool extends BaseDbTool
{
    protected string $description = 'List all categories for a given module (e.g. finance, product)';

    public function schema(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()->description('Module slug')->required(),
            'type' => $schema->string()->description('Filter by type: income, expense'),
        ];
    }

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessAllowed($request, 'mcp:tools.read')) {
            return $denied;
        }

        $request->validate([
            'module' => 'required|string',
            'type' => 'nullable|string',
        ]);

        $categories = Category::forModule($request->get('module'))
            ->active()
            ->when($request->filled('type'), fn ($q) => $q->ofType($request->get('type')))
            ->latest()
            ->get();

        return Response::text($categories->toJson(JSON_PRETTY_PRINT));
    }
}
