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
            'module' => $schema->string()->description('Module slug')->maxLength(50)->required(),
            'type' => $schema->string()->description('Filter by type: income, expense')->enum(['income', 'expense']),
        ];
    }

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessAllowed($request, 'mcp:tools.read')) {
            return $denied;
        }

        $validated = $request->validate([
            'module' => ['required', 'string', 'max:50', 'alpha_dash'],
            'type' => ['nullable', 'string', 'in:income,expense'],
        ]);

        $categories = Category::forModule($validated['module'])
            ->active()
            ->when(! empty($validated['type']), fn ($q) => $q->ofType($validated['type']))
            ->latest()
            ->get();

        return Response::text($categories->toJson(JSON_PRETTY_PRINT));
    }
}
