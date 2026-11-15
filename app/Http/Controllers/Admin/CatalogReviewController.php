<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Services\CatalogReviewDates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Mark as reviewed": a team member checked this record against its sources.
 * The route group already requires CMS access and, through CheckCatalogRevision, rejects the click
 * when someone changed the record after the page was opened.
 */
class CatalogReviewController extends Controller
{
    public function governmentId(Request $request, GovernmentId $governmentId, CatalogReviewDates $dates): RedirectResponse
    {
        return $this->mark($request, $governmentId, $dates);
    }

    public function document(Request $request, Document $document, CatalogReviewDates $dates): RedirectResponse
    {
        return $this->mark($request, $document, $dates);
    }

    private function mark(Request $request, GovernmentId|Document $record, CatalogReviewDates $dates): RedirectResponse
    {
        $request->validate(['_catalog_revision' => ['required', 'string']],
            ['_catalog_revision.required' => 'Reload the page, check the record, then mark it as reviewed.']);
        $dates->markReviewed($request->user(), $record);

        return back()->with('success', 'Marked as reviewed today. Last Updated will show if the information changes later.');
    }
}
