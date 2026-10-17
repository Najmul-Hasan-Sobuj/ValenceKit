<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and apply security response headers.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $nonce = Vite::cspNonce();

        $trackingScriptSrc = 'https://www.clarity.ms https://*.clarity.ms https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net';
        $trackingConnectSrc = 'https://*.clarity.ms https://www.google-analytics.com https://analytics.google.com https://stats.g.doubleclick.net https://www.facebook.com';

        if (app()->environment('local', 'testing')) {
            $scriptSrc = "'self' 'unsafe-eval' 'nonce-{$nonce}' http://localhost:5173 http://127.0.0.1:5173 {$trackingScriptSrc}";
            $connectSrc = "'self' http://localhost:5173 ws://localhost:5173 http://127.0.0.1:5173 ws://127.0.0.1:5173 https:";
        } else {
            $scriptSrc = "'self' 'nonce-{$nonce}' {$trackingScriptSrc}";
            $connectSrc = "'self' {$trackingConnectSrc}";
        }

        $headers = [
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "script-src {$scriptSrc}",
                "style-src 'self' 'unsafe-inline' https: http:",
                "img-src 'self' data: blob: https:",
                "font-src 'self' data: https:",
                "connect-src {$connectSrc}",
                "frame-src 'self' https://www.googletagmanager.com",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), publickey-credentials-get=(self)',
        ];

        if ($request->isSecure() || app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        $response->headers->add($headers);

        return $response;
    }
}
