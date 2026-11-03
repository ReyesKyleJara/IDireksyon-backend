<?php

namespace App\Http\Middleware;

use App\Models\Document;
use App\Models\GovernmentId;
use App\Services\CatalogRevision;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckCatalogRevision
{
    public function handle(Request $request, Closure $next)
    {
        $record = collect($request->route()->parameters())->first(fn ($value) => $value instanceof Document || $value instanceof GovernmentId);
        if ($request->isMethod('GET') || ! $record) return $next($request);

        return DB::transaction(function () use ($request, $next, $record) {
            $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $expected = $request->input('_catalog_revision') ?? $request->header('X-Catalog-Revision');
            if ($expected !== null && (! is_string($expected) || ! hash_equals(CatalogRevision::token($record), $expected))) {
                throw ValidationException::withMessages(['_catalog_revision' =>
                    'Another editor changed this record. Your submission has not been saved. Copy your draft, then reload and review the latest record before saving again.']);
            }
            $response = $next($request);
            if ($expected !== null && $response instanceof \Illuminate\Http\JsonResponse && $response->isSuccessful() && $record->fresh()) {
                $response->setData([...$response->getData(true), '_catalog_revision' => CatalogRevision::token($record)]);
            }
            return $response;
        });
    }
}
