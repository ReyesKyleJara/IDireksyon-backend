<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\GovernmentIdController;
use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Contracts\View\View;

/**
 * Public, read-only preview of one ID or document, opened from the app's Share button.
 * It shows a summary built from the same data the app API sends (same wording, same public
 * sources), so nothing private or CMS-only can appear here. No login, no forms.
 */
class PublicRecordController extends Controller
{
    public function governmentId(GovernmentId $governmentId): View
    {
        return $this->page(app(GovernmentIdController::class)->show($governmentId)->getData(true)['data'], 'ids', 'ID');
    }

    public function document(Document $document): View
    {
        return $this->page(app(DocumentController::class)->show($document)->getData(true)['data'], 'documents', 'Document');
    }

    private function page(array $record, string $path, string $kind): View
    {
        $research = is_array($record['research_metadata'] ?? null) ? $record['research_metadata'] : [];
        $hidden = is_array($research['hidden_sections'] ?? null) ? $research['hidden_sections'] : [];
        // Same rule as the app: http(s) only, a real host, no embedded credentials.
        $safe = function ($url) {
            $parts = is_string($url) ? parse_url(trim($url)) : false;

            return is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                && filled($parts['host'] ?? null) && ! isset($parts['user']) && ! isset($parts['pass']);
        };

        return view('public.record', [
            'record' => $record,
            'kind' => $kind,
            'shareUrl' => url("/{$path}/{$record['id']}"),
            'imageUrl' => filled($record['image_url'] ?? null) ? url($record['image_url']) : null,
            'eligibility' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($record['eligibility'] ?? ''))))),
            'fees' => in_array('fees', $hidden, true) ? [] : array_values(array_filter($record['fees'] ?? [], 'is_array')),
            'sources' => collect($research['sources'] ?? [])->filter(fn ($source) => is_array($source) && $safe($source['url'] ?? null))->values()->all(),
            'waysToApply' => count($record['request_paths'] ?? []),
        ]);
    }
}
