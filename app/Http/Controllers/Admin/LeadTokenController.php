<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadTokenController extends Controller
{
    public function store(Request $request, AuditLogService $audit): JsonResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin && $admin->can('leads.import'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $result = $admin->createToken($data['name'], ['leads:import']);
        $accessToken = $admin->tokens()->whereKey($result->accessTokenId)->firstOrFail();
        $accessToken->update(['expires_at' => min($accessToken->expires_at, now()->addDays(30))]);
        $audit->record('lead.token_created', actor: $admin);

        return response()->json(['token' => $result->accessToken, 'expires_at' => $accessToken->expires_at])
            ->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, string $token, AuditLogService $audit): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin && $admin->can('leads.import'), 403);
        $accessToken = $admin->tokens()->whereKey($token)->firstOrFail();
        abort_unless($accessToken->scopes === ['leads:import'], 404);
        $accessToken->revoke();
        $audit->record('lead.token_revoked', actor: $admin);

        return back()->with('success', 'Webhook token revoked.');
    }
}
