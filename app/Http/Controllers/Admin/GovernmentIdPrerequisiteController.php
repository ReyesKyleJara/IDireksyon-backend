<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdPrerequisite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GovernmentIdPrerequisiteController extends Controller
{
    public function index(GovernmentId $governmentId)
    {
        return view('admin.government_ids.prerequisites.index', [
            'governmentId' => $governmentId,
            'prerequisites' => $governmentId->prerequisites()->with(['profile', 'prerequisiteGovernmentId', 'prerequisiteDocument'])->get(),
        ]);
    }

    public function create(GovernmentId $governmentId)
    {
        return $this->form($governmentId, new GovernmentIdPrerequisite(['sort_order' => 0]));
    }

    public function edit(GovernmentId $governmentId, string $prerequisite)
    {
        return $this->form($governmentId, $governmentId->prerequisites()->findOrFail($prerequisite));
    }

    private function form(GovernmentId $governmentId, GovernmentIdPrerequisite $prerequisite)
    {
        return view('admin.government_ids.prerequisites.form', [
            'governmentId' => $governmentId, 'prerequisite' => $prerequisite,
            'profiles' => $governmentId->requirementSets()->get(),
            'ids' => GovernmentId::whereKeyNot($governmentId->id)->orderBy('name')->get(['id', 'name']),
            'documents' => Document::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, GovernmentId $governmentId)
    {
        return $this->save($request, $governmentId, new GovernmentIdPrerequisite);
    }

    public function update(Request $request, GovernmentId $governmentId, string $prerequisite)
    {
        return $this->save($request, $governmentId, $governmentId->prerequisites()->findOrFail($prerequisite));
    }

    private function save(Request $request, GovernmentId $governmentId, GovernmentIdPrerequisite $prerequisite)
    {
        // The same model boundary validates CMS writes and other backend callers.
        DB::transaction(function () use ($request, $governmentId, $prerequisite) {
            $prerequisite->fill([
                ...array_fill_keys(['requirement_set_id', 'prerequisite_government_id_id', 'prerequisite_document_id', 'note'], null),
                'sort_order' => 0,
                ...$request->only(['requirement_set_id', 'prerequisite_government_id_id', 'prerequisite_document_id', 'note', 'sort_order']),
                'government_id_id' => $governmentId->id,
            ])->save();
            ContentChangeLog::record($governmentId, 'updated', ['prerequisites']);
        });

        return redirect()->route('admin.government-ids.prerequisites.index', $governmentId)->with('success', 'Prerequisite saved.');
    }

    public function destroy(GovernmentId $governmentId, string $prerequisite)
    {
        DB::transaction(function () use ($governmentId, $prerequisite) {
            $governmentId->prerequisites()->findOrFail($prerequisite)->delete();
            ContentChangeLog::record($governmentId, 'updated', ['prerequisites']);
        });

        return redirect()->route('admin.government-ids.prerequisites.index', $governmentId)->with('success', 'Prerequisite removed.');
    }
}
