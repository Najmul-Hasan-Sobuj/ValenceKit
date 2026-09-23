<?php

namespace App\Http\Middleware\Admin;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();

            return $request->expectsJson()
                ? response()->json(['message' => 'Admin account is inactive.'], 403)
                : redirect()->route('admin.login');
        }

        return $next($request);
    }
}
