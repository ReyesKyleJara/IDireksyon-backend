<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ContentChangeLog, Document, GovernmentId};
use App\Services\CatalogSpecialCases;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogSpecialCaseController extends Controller
{
    public function documentEdit(Request $request, Document $document) { return $this->edit($request); }
    public function governmentIdEdit(Request $request, GovernmentId $governmentId) { return $this->edit($request); }
    public function documentSave(Request $request, Document $document) { return $this->save($request); }
    public function governmentIdSave(Request $request, GovernmentId $governmentId) { return $this->save($request); }
    public function documentAction(Request $request, Document $document) { return $this->action($request); }
    public function governmentIdAction(Request $request, GovernmentId $governmentId) { return $this->action($request); }
    private function owner(Request $request): Document|GovernmentId
    {
        return $request->route('document') ?? $request->route('governmentId');
    }

    public function edit(Request $request)
    {
        $owner = $this->owner($request);
        $specialCase = $request->route('specialCase');
        $case = $specialCase ? CatalogSpecialCases::find($owner, $specialCase) : [];
        $recordRoutes = $owner instanceof Document ? 'admin.documents' : 'admin.government-ids';
        $requirementOptions = GovernmentId::orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'type' => 'government_id'])
            ->concat(Document::orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'type' => 'document']))->values()->all();
        return view('admin.catalog.special-case', ['governmentId' => $owner, 'case' => $case, 'recordRoutes' => $recordRoutes, 'requirementOptions' => $requirementOptions]);
    }

    public function save(Request $request)
    {
        $request->validate(['case_id' => ['nullable', 'uuid']]);
        $owner = $this->owner($request);
        $id = $request->input('case_id');
        if ($id !== null) CatalogSpecialCases::find($owner, (string) $id);
        $data = CatalogSpecialCases::read($request, $owner);
        $metadata = $owner->fresh()->research_metadata ?? ['status' => 'draft'];
        $cases = CatalogSpecialCases::all($owner);
        abort_if($id === null && count($cases) >= 30, 422, 'A catalog item can have at most 30 special cases.');
        $saved = [...($id === null ? [] : CatalogSpecialCases::find($owner, (string) $id)), 'id' => $id ?? (string) Str::uuid(), ...$data];
        $index = $id === null ? count($cases) : array_search($id, array_column($cases, 'id'), true);
        $cases[$index] = $saved;
        $metadata['special_cases'] = array_values($cases);
        $owner->update(['research_metadata' => $metadata]);
        ContentChangeLog::record($owner, 'updated', ['special_cases']);
        $routes = $owner instanceof Document ? 'admin.documents' : 'admin.government-ids';
        return redirect()->route($routes.'.special-cases.edit', [$owner, $saved['id']])->with('success', 'Special case guide saved. Normal request channels are unchanged.');
    }

    public function action(Request $request)
    {
        $data = $request->validate(['case_id' => ['required', 'string'], 'action' => ['required', 'in:delete,up,down']]);
        $owner = $this->owner($request);
        CatalogSpecialCases::find($owner, $data['case_id']);
        $cases = CatalogSpecialCases::all($owner);
        $index = array_search($data['case_id'], array_column($cases, 'id'), true);
        if ($data['action'] === 'delete') array_splice($cases, $index, 1);
        else {
            $next = $index + ($data['action'] === 'up' ? -1 : 1);
            if (isset($cases[$next])) [$cases[$index], $cases[$next]] = [$cases[$next], $cases[$index]];
        }
        $metadata = $owner->research_metadata ?? [];
        $metadata['special_cases'] = array_values($cases);
        $owner->update(['research_metadata' => $metadata]);
        ContentChangeLog::record($owner, 'updated', ['special_cases']);
        return back()->with('success', 'Special case list updated.');
    }
}
