<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser protections for every response.
 *
 * The content policy is report-only: browsers list violations in the console
 * but block nothing yet. Its 'unsafe-inline' and 'unsafe-eval' are there because
 * Alpine and two inline scripts need them today.
 */
class SecurityHeaders
{
    private const CONTENT_POLICY = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "frame-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
        "font-src 'self' data: https://fonts.bunny.net",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        // Same origin, not deny: the CMS shows its own office form in an iframe.
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $headers->set('Content-Security-Policy-Report-Only', implode('; ', self::CONTENT_POLICY));

        $maxAge = config('security.hsts_max_age');
        if ($request->secure() && $maxAge > 0) {
            $headers->set('Strict-Transport-Security', 'max-age='.$maxAge);
        }

        return $response;
    }
}
