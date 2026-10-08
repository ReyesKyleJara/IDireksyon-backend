<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GovernmentIdRequirementSetRequest;
use App\Models\ContentChangeLog;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementSet;
use Illuminate\Support\Facades\DB;

class GovernmentIdRequirementSetController extends Controller
{
    public function index(GovernmentId $governmentId)
    {
        return view('admin.government_ids.requirement_sets.index', [
            'governmentId' => $governmentId,
            'sets' => $governmentId->requirementSets()->paginate(15),
        ]);
    }

    public function create(GovernmentId $governmentId)
    {
        return $this->form($governmentId, new GovernmentIdRequirementSet());
    }

    public function edit(GovernmentId $governmentId, string $requirementSet)
    {
        return $this->form($governmentId, $governmentId->requirementSets()->findOrFail($requirementSet));
    }

    private function form(GovernmentId $governmentId, GovernmentIdRequirementSet $set)
    {
        return view('admin.government_ids.requirement_sets.form', [
            'governmentId' => $governmentId,
            'set' => $set,
            'applicationTypes' => GovernmentIdRequirementSet::APPLICATION_TYPES,
            'applicantTypes' => GovernmentIdRequirementSet::APPLICANT_TYPES,
        ]);
    }

    public function store(GovernmentIdRequirementSetRequest $request, GovernmentId $governmentId)
    {
        DB::transaction(function () use ($request, $governmentId) {
            $governmentId->requirementSets()->create($this->setData($request));
            ContentChangeLog::record($governmentId, 'updated', ['requirement_sets']);
        });

        return redirect()->route('admin.government-ids.requirement-sets.index', $governmentId)
            ->with('success', 'Requirement set added.');
    }

    public function update(GovernmentIdRequirementSetRequest $request, GovernmentId $governmentId, string $requirementSet)
    {
        $set = $governmentId->requirementSets()->findOrFail($requirementSet);
        DB::transaction(function () use ($request, $governmentId, $set) {
            $set->fill($this->setData($request));
            $set->save();
            if ($set->wasChanged(['application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom', 'min_age', 'max_age'])) {
                ContentChangeLog::record($governmentId, 'updated', ['requirement_sets']);
            }
        });

        return redirect()->route('admin.government-ids.requirement-sets.index', $governmentId)
            ->with('success', 'Requirement set updated.');
    }

    public function destroy(GovernmentId $governmentId, string $requirementSet)
    {
        $set = $governmentId->requirementSets()->findOrFail($requirementSet);
        DB::transaction(function () use ($governmentId, $set) {
            if ($set->applicationSteps()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'application_guide' => 'This scenario has an Application Guide. Remove its steps before deleting the scenario.',
                ]);
            }
            $set->delete();
            ContentChangeLog::record($governmentId, 'updated', ['requirement_sets']);
        });

        return redirect()->route('admin.government-ids.requirement-sets.index', $governmentId)
            ->with('success', 'Requirement set deleted.');
    }

    private function setData(GovernmentIdRequirementSetRequest $request): array
    {
        // Each form saves the complete set. Blank optional values clear old input.
        return array_replace([
            'application_type_custom' => null,
            'applicant_type_custom' => null,
            'min_age' => null,
            'max_age' => null,
        ], $request->safe()->only([
            'application_type', 'application_type_custom',
            'applicant_type', 'applicant_type_custom', 'min_age', 'max_age',
        ]));
    }
}
