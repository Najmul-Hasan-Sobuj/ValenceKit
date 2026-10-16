<?php

namespace App\Mcp\Tools;

use App\Models\Admin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Get the roles and permissions assigned to a specific admin by their email address.')]
class GetUserRoles extends BaseDbTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessAllowed($request, 'mcp:users.read')) {
            return $denied;
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = $validated['email'];
        $admin = Admin::with('roles')->where('email', $email)->first();

        if (! $admin) {
            return Response::text('Admin not found with email: '.$email);
        }

        $roles = $admin->roles->pluck('name')->implode(', ');

        return Response::text(
            $roles
                ? "The admin '{$admin->name}' has the following roles: {$roles}."
                : "The admin '{$admin->name}' does not have any roles assigned."
        );
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'email' => $schema->string()->description('The admin email address to look up')->format('email')->maxLength(255)->required(),
        ];
    }
}
