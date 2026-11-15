<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Support\ResidentVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Hide Entry / Show Entry. Hiding only sets hidden_at: no information is deleted or changed, the
 * record stays fully editable in the CMS, and showing it again restores it as it was.
 * Separate from Last Reviewed, Agency Confirmation and the research status.
 */
class CatalogVisibilityController extends Controller
{
    public function governmentId(Request $request, GovernmentId $governmentId): RedirectResponse
    {
        return $this->set($request, $governmentId);
    }

    public function document(Request $request, Document $document): RedirectResponse
    {
        return $this->set($request, $document);
    }

    private function set(Request $request, GovernmentId|Document $record): RedirectResponse
    {
        $hide = $request->validate(['hidden' => ['required', 'boolean']])['hidden'];
        if ((bool) $hide !== $record->isHidden()) {
            $values = ['hidden_at' => $hide ? now() : null, 'hidden_by' => $hide ? $request->user()->getKey() : null];
            // A direct query: Last Updated and updated_at are not touched by hiding or showing.
            $record->newQueryWithoutScopes()->whereKey($record->getKey())->toBase()->update($values);
            $record->forceFill($values)->syncOriginalAttributes(array_keys($values));
            ResidentVisibility::forget();
            ContentChangeLog::record($record, $hide ? 'hidden' : 'shown', ['hidden_at']);
        }

        return back()->with('success', $hide
            ? 'Entry hidden. Residents can no longer see it in the app. Nothing was deleted.'
            : 'Entry shown. Residents can see it in the app again.');
    }
}
