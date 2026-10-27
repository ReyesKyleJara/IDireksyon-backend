<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Services\GovernmentIdApplicationGuide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GovernmentIdCreationWizardController extends Controller
{
    use Concerns\PreparesCatalogEntry;
    use Concerns\EditsCatalogWizard;

    public const STEPS = [
        'basic' => 'Basic Info',
        'requirements' => 'Requirements',
        'fees' => 'Fees',
        'guide' => 'Application Guide',
        'offices' => 'Offices',
        'review' => 'Review',
    ];

    private const BASIC_FIELDS = [
        'application_availability',
        'acquisition_type',
        'target_group',
        'guidance_mode',
        'include_in_smart_sequencing',
        'name',
        'level',
        'category',
        'agency_id',
        'purpose',
        'description',
        'eligibility_age_type',
        'eligibility_min_age',
        'eligibility_max_age',
        'eligibility_citizenship',
        'eligibility_residency',
        'eligibility_residency_custom',
        'eligibility_other_conditions',
        'validity_type',
        'validity_value',
        'validity_unit',
        'processing_time_type',
        'processing_time_min',
        'processing_time_max',
        'processing_time_unit',
        'official_link',
        'official_sources',
    ];

    public function create(Request $request)
    {
        $creationKey = (string) Str::uuid();

        $starts = $request->session()->get('id_creation_starts', []);
        $starts[$creationKey] = null;

        $request->session()->put('id_creation_starts', $starts);

        return $this->page(
            $request,
            null,
            'basic',
            $creationKey
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'creation_key' => ['required', 'uuid'],
        ]);

        $starts = $request->session()->get('id_creation_starts', []);
        $key = $request->input('creation_key');

        abort_unless(
            array_key_exists($key, $starts),
            419,
            'Open Add Government ID again to start creation.'
        );

        // Repeated submission from the same creation form
        // opens the original ID.
        if ($starts[$key] !== null) {
            return redirect()->route(
                'admin.government-ids.wizard.show',
                [$starts[$key], 'requirements']
            );
        }

        $governmentId = DB::transaction(function () use ($request) {
            $governmentId = GovernmentId::create($this->basicData($request));
            $governmentId->requirementSets()->create([
                'application_type' => 'new',
                'applicant_type' => 'all',
                'application_type_custom' => null,
                'applicant_type_custom' => null,
                'min_age' => null,
                'max_age' => null,
            ]);

            return $governmentId;
        });

        $starts[$key] = $governmentId->id;

        $request->session()->put(
            'id_creation_starts',
            $starts
        );

        $this->markStep(
            $request,
            $governmentId,
            'basic',
            'requirements'
        );

        return redirect()
            ->route(
                'admin.government-ids.wizard.show',
                [$governmentId, 'requirements']
            )
            ->with(
                'success',
                'Basic information saved. Continue by adding the requirements for this ID.'
            );
    }

    public function show(
        Request $request,
        GovernmentId $governmentId,
        string $step
    ) {
        abort_unless(
            array_key_exists($step, self::STEPS),
            404
        );

        $request->session()->put(
            'id_creation_resume',
            [
                'id' => $governmentId->id,
                'step' => $step,
            ]
        );

        return $this->page(
            $request,
            $governmentId,
            $step
        );
    }

    // Retire wizard-only case actions while preserving existing route definitions.
    public function storeCase()
    {
        abort(404);
    }

    public function destroyCase()
    {
        abort(404);
    }

    public function edit(Request $request, GovernmentId $governmentId, string $step = 'basic')
    {
        abort_unless(array_key_exists($step, self::STEPS), 404);
        $request->attributes->set('catalog_wizard_editing', true);

        return $this->page($request, $governmentId, $step);
    }

    public function saveEdit(Request $request, GovernmentId $governmentId, string $step)
    {
        $request->attributes->set('catalog_wizard_editing', true);

        return $this->save($request, $governmentId, $step);
    }

    public function save(
        Request $request,
        GovernmentId $governmentId,
        string $step
    ) {
        abort_unless(
            array_key_exists($step, self::STEPS),
            404
        );

        DB::transaction(function () use (
            $request,
            $governmentId,
            $step
        ) {
            switch ($step) {
                case 'basic':
                    $governmentId->update(
                        $this->editBasicData($request, $governmentId, $this->basicData($request))
                    );
                    break;

                case 'requirements':
                    if ($request->has('prerequisites_present')) {
                        $input = $request->validate(['prerequisites' => ['sometimes', 'array'], 'prerequisites_present' => ['in:1']]);
                        app(\App\Services\CatalogPrerequisites::class)->save($governmentId, $input['prerequisites'] ?? []);
                    }
                    break;

                case 'fees':
                    // Reuse catalog validation,
                    // scoped to this section only.
                    $input = Request::create(
                        '/',
                        'POST',
                        [
                            'name' => $governmentId->name,
                            ...$request->only('fees'),
                        ]
                    );

                    $data = $this->validateCatalogEntry(
                        $input
                    );

                    $this->syncFees(
                        $governmentId,
                        $this->prepareFeeRows(
                            $data['fees'] ?? []
                        ),
                        true
                    );
                    break;

                case 'guide':
                    $guide = app(
                        GovernmentIdApplicationGuide::class
                    );

                    $guide->sync(
                        $governmentId,
                        $this->editingWizard($request)
                            ? $this->editGuideRows($governmentId, $guide->read($request))
                            : $guide->read($request)
                    );
                    break;

                case 'offices':
                    $rows = $this->prepareOfficeLinks(
                        $request
                    );

                    if ($rows !== null) {
                        $this->syncOfficeLinks(
                            $governmentId,
                            $rows
                        );
                    }
                    break;

                // Requirements
                // save through their own existing endpoints.
            }
        });

        if ($step === 'review') {
            if (! $this->editingWizard($request)) {
                $request->session()->forget('id_creation_resume');
            }

            return redirect()
                ->route(
                    'admin.government-ids.show',
                    $governmentId
                )
                ->with(
                    'success',
                    $this->editingWizard($request) ? 'Government ID editing finished.' : 'Government ID creation finished.'
                );
        }

        $keys = array_keys(self::STEPS);

        $next = $keys[
            array_search(
                $step,
                $keys,
                true
            ) + 1
        ];

        $this->markStep(
            $request,
            $governmentId,
            $step,
            $next
        );

        return redirect()
            ->route(
                $this->wizardRoutes($request, 'government-ids').'.show',
                [$governmentId, $next]
            )
            ->with(
                'success',
                'Saved. You can return to any section.'
            );
    }

    private function basicData(
        Request $request
    ): array {
        $input = Request::create(
            '/',
            'POST',
            $request->only(
                self::BASIC_FIELDS
            )
        );

        return $this->prepareProcessingTime(
            $this->prepareValidity(
                $this->prepareEligibility(
                    $this->validateCatalogEntry(
                        $input
                    )
                )
            )
        );
    }

    private function markStep(
        Request $request,
        GovernmentId $governmentId,
        string $step,
        string $next
    ): void {
        if ($this->editingWizard($request)) {
            $request->session()->put("id_edit_progress.{$governmentId->id}.{$step}", true);
            return;
        }

        $request->session()->put(
            "id_creation_progress.{$governmentId->id}.{$step}",
            true
        );

        $request->session()->put(
            'id_creation_resume',
            [
                'id' => $governmentId->id,
                'step' => $next,
            ]
        );
    }

    private function page(
        Request $request,
        ?GovernmentId $governmentId,
        string $step,
        ?string $creationKey = null
    ) {
        $governmentId?->load([
            'prerequisites.governmentId',
            'prerequisites.document',
            'agency',
            'requirementSets.groups.items.governmentId',
            'requirementSets.groups.items.document',
            'requirementSets.groups.ways',
            'requirementSets.applicationSteps.blocks',
            'fees',
            'offices.agency',
        ]);

        $sets = $governmentId?->requirementSets
            ?? collect();

        $groups = $sets->flatMap->groups;

        $counts = [
            'groups' => $groups->count(),
            'items' => $groups->sum(
                fn ($group) => $group
                    ->items
                    ->count()
            ),
            'fees' => $governmentId?->fees->count() ?? 0,
            'guide' => $sets->sum(
                fn ($set) => $set
                    ->applicationSteps
                    ->count()
            ),
            'offices' => $governmentId?->offices->count() ?? 0,
        ];

        $resume = $request
            ->session()
            ->get('id_creation_resume');

        $resumeId = $resume
            ? GovernmentId::find(
                $resume['id']
            )
            : null;

        return view(
            'admin.government_ids.wizard.page',
            [
                'governmentId' => $governmentId,
                'editing' => $this->editingWizard($request),
                'step' => $step,
                'steps' => self::STEPS,
                'creationKey' => $creationKey,
                'counts' => $counts,

                'requirementsIncomplete' =>
                    $sets->isEmpty()
                    || $sets->contains(
                        fn ($set) =>
                            $set->groups->isEmpty()
                    ),

                'completed' => $governmentId
                    ? $request->session()->get(
                        ($this->editingWizard($request) ? "id_edit_progress" : "id_creation_progress").".{$governmentId->id}",
                        []
                    )
                    : [],

                'resume' => $resumeId
                    ? [
                        'record' => $resumeId,
                        'step' => array_key_exists($resume['step'], self::STEPS)
                            ? $resume['step']
                            : 'requirements',
                    ]
                    : null,

                'agencies' => Agency::orderBy('name')
                    ->get(),

                'offices' => $step === 'offices'
                    ? Office::with('agency')
                        ->orderBy('name')
                        ->get()
                    : collect(),

                'checklists' => $sets
                    ->when(! $this->editingWizard($request), fn ($rows) => $rows
                        ->filter(fn ($set) => $set->application_type === 'new' && $set->applicant_type === 'all')
                        ->take(1))
                    ->map(
                        fn ($set) =>
                            GovernmentIdChecklistController::present(
                                $set
                            )
                    )
                    ->values()
                    ->all(),

                'requirementOptions' =>
                    $step === 'requirements'
                        ? GovernmentId::orderBy('name')
                            ->get(['id', 'name'])
                            ->map(
                                fn ($id) => [
                                    'id' => $id->id,
                                    'name' => $id->name,
                                    'type' => 'government_id',
                                ]
                            )
                            ->concat(
                                Document::orderBy('name')
                                    ->get(['id', 'name'])
                                    ->map(
                                        fn ($document) => [
                                            'id' => $document->id,
                                            'name' => $document->name,
                                            'type' => 'document',
                                        ]
                                    )
                            )
                            ->sortBy('name')
                            ->values()
                            ->all()
                        : [],
            ]
        );
    }
}