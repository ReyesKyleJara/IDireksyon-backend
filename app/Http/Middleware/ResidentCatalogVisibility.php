<?php

namespace App\Http\Middleware;

use App\Support\ResidentVisibility;
use Closure;
use Illuminate\Http\Request;

/** Resident-facing requests never see hidden IDs or documents (see ResidentVisibility). */
class ResidentCatalogVisibility
{
    public function handle(Request $request, Closure $next)
    {
        return ResidentVisibility::during(fn () => $next($request));
    }
}
