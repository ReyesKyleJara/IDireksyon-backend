<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementSet;
use App\Models\ContentChangeLog;
use App\Services\CatalogEncoding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogChannelController extends Controller
{
    public function document(Request $request, Document $document) { return $this->save($request, $document, 'documents'); }
    public function governmentId(Request $request, GovernmentId $governmentId) { return $this->save($request, $governmentId, 'government-ids'); }
    public function documentEdit(Request $request, Document $document, ?string $pathId = null) { return $this->editor($request, $document, 'documents', $pathId); }
    public function governmentIdEdit(Request $request, GovernmentId $governmentId, ?string $pathId = null) { return $this->editor($request, $governmentId, 'government-ids', $pathId); }
    public function documentDelete(Request $request, Document $document, string $pathId) { return $this->remove($request, $document, 'documents', $pathId); }
    public function governmentIdDelete(Request $request, GovernmentId $governmentId, string $pathId) { return $this->remove($request, $governmentId, 'government-ids', $pathId); }

    private function editor(Request $request, Document|GovernmentId $owner, string $resource, ?string $pathId)
    {
        $request->validate(['wizard' => ['nullable', 'in:create,edit']]);
        $path = $pathId === null ? null : $owner->requirementSets()->findOrFail($pathId);
        return view('admin.catalog.request-option', [
            'governmentId' => $owner, 'path' => $path, 'recordRoutes' => 'admin.'.$resource,
            'wizardContext' => $request->query('wizard', 'edit'),
        ]);
    }

    private function remove(Request $request, Document|GovernmentId $owner, string $resource, string $pathId)
    {
        $request->validate(['return_wizard' => ['nullable', 'in:create,edit']]);
        $set = $owner->requirementSets()->findOrFail($pathId);
        if ($set->groups()->exists() || $set->applicationSteps()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['request_option' => 'This option has saved requirements or application steps. Review those sections before deleting it. Nothing was removed.']);
        }
        $set->delete();
        ContentChangeLog::record($owner, 'updated', ['request_channels']);
        $wizard = $request->input('return_wizard') === 'create' ? 'wizard' : 'edit-wizard';
        return redirect()->route('admin.'.$resource.'.'.$wizard.'.show', [$owner, 'channels'])->with('success', 'Request option deleted. Shared fees, offices, and special case guides were kept.');
    }

    private function save(Request $request, Document|GovernmentId $owner, string $resource)
    {
        if ($request->has('inline_payload')) {
            return response()->json(app(\App\Services\InlineRequestMethod::class)->save($request, $owner));
        }
        $data = $request->validate([
            'return_wizard' => ['nullable', 'in:create,edit'],
            'path_id' => ['nullable', 'integer'],
            'application_type' => ['required', Rule::in(array_keys(GovernmentIdRequirementSet::APPLICATION_TYPES))],
            'application_type_custom' => ['required_if:application_type,custom', 'nullable', 'string', 'max:255'],
            'applicant_type' => ['required', Rule::in(array_keys(GovernmentIdRequirementSet::APPLICANT_TYPES))],
            'applicant_type_custom' => ['nullable', 'string', 'max:255'],
            'min_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:150', 'gte:min_age'],
            'request_channel' => ['required', 'array'],
            'request_channel.troubleshooting' => ['sometimes', 'array'],
            'request_channel.troubleshooting.*' => ['array:title,body,url'],
        ]);
        $channel = $request->input('request_channel');
        // HTML multiselects omit empty lists. Their explicit scope controls preserve all/none semantics.
        foreach (['fee_ids', 'office_ids'] as $field) {
            $scope = $request->input($field.'_scope', 'all');
            abort_unless(in_array($scope, ['all', 'selected'], true), 422);
            $channel[$field] = $scope === 'all' ? null : ($channel[$field] ?? []);
        }
        $channel['fulfillment'] ??= [];
        $channel['troubleshooting'] = array_values(array_filter($channel['troubleshooting'] ?? [], fn ($row) => filled($row['title'] ?? null) || filled($row['body'] ?? null) || filled($row['url'] ?? null)));
        $set = DB::transaction(function () use ($owner, $data, $channel) {
            $set = ! empty($data['path_id']) ? $owner->requirementSets()->findOrFail($data['path_id']) : $owner->requirementSets()->make();
            $set->fill(collect($data)->except(['path_id', 'request_channel', 'return_wizard'])->all());
            $set->request_channel = CatalogEncoding::channel($channel, $owner);
            $set->save();
            ContentChangeLog::record($owner, 'updated', ['request_channels']);
            return $set;
        });
        $wizard = ($data['return_wizard'] ?? null) === 'create' ? 'wizard' : 'edit-wizard';
        $url = route('admin.'.$resource.'.'.$wizard.'.show', [$owner, 'channels', 'path' => $set->id]);
        if ($request->boolean('option_editor')) $url .= '#related-content';
        return redirect()->to($url)->with('success', 'Request option saved. Manage its related content below.');
    }
}
