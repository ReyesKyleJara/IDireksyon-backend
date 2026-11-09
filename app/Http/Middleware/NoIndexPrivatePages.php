<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asks search engines to leave CMS pages out of their results. This only keeps
 * them out of search: access is still enforced by the auth and role checks.
 */
class NoIndexPrivatePages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('admin', 'admin/*', 'login', 'dashboard', 'reset-password', 'reset-password/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
