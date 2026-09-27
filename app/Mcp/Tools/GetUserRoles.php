<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Get the roles and permissions assigned to a specific user by their email address.')]
class GetUserRoles extends BaseDbTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $email = $request->get('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            return Response::text('User not found with email: '.$email);
        }

        $roles = $user->roles->pluck('name')->implode(', ');

        return Response::text(
            $roles
                ? "The user '{$user->name}' has the following roles: {$roles}."
                : "The user '{$user->name}' does not have any roles assigned."
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
            'email' => $schema->string('The email address of the user to look up')->required(),
        ];
    }
}
