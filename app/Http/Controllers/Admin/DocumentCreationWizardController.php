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

class DocumentCreationWizardController extends Controller
{
    use Concerns\PreparesCatalogEntry;
    use Concerns\EditsCatalogWizard;

    public const STEPS = GovernmentIdCreationWizardController::STEPS;

    private const BASIC_FIELDS = [
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
        'issued_by',
    ];

    public function create(Request $request)
    {
        $creationKey = (string) Str::uuid();

        $starts = $request->session()->get('document_creation_starts', []);
        $starts[$creationKey] = null;

        $request->session()->put('document_creation_starts', $starts);

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

        $starts = $request->session()->get('document_creation_starts', []);
        $key = $request->input('creation_key');

        abort_unless(
            array_key_exists($key, $starts),
            419,
            'Open Add Document again to start creation.'
        );

        // Repeated submission from the same creation form
        // opens the original ID.
        if ($starts[$key] !== null) {
            return redirect()->route(
                'admin.documents.wizard.show',
                [$starts[$key], 'requirements']
            );
        }

        $document = DB::transaction(function () use ($request) {
            $document = Document::create($this->basicData($request));
            $document->requirementSets()->create([
                'application_type' => 'new',
                'applicant_type' => 'all',
                'application_type_custom' => null,
                'applicant_type_custom' => null,
                'min_age' => null,
                'max_age' => null,
            ]);

            return $document;
        });

        $starts[$key] = $document->id;

        $request->session()->put(
            'document_creation_starts',
            $starts
        );

        $this->markStep(
            $request,
            $document,
            'basic',
            'requirements'
        );

        return redirect()
            ->route(
                'admin.documents.wizard.show',
                [$document, 'requirements']
            )
            ->with(
                'success',
                'Basic information saved. Continue by adding the requirements for this document.'
            );
    }

    public function show(
        Request $request,
        Document $document,
        string $step
    ) {
        abort_unless(
            array_key_exists($step, self::STEPS),
            404
        );

        $request->session()->put(
            'document_creation_resume',
            [
                'id' => $document->id,
                'step' => $step,
            ]
        );

        return $this->page(
            $request,
            $document,
            $step
        );
    }

    public function edit(Request $request, Document $document, string $step = 'basic')
    {
        abort_unless(array_key_exists($step, self::STEPS), 404);
        $request->attributes->set('catalog_wizard_editing', true);

        return $this->page($request, $document, $step);
    }

    public function saveEdit(Request $request, Document $document, string $step)
    {
        $request->attributes->set('catalog_wizard_editing', true);

        return $this->save($request, $document, $step);
    }

    public function save(
        Request $request,
        Document $document,
        string $step
    ) {
        abort_unless(
            array_key_exists($step, self::STEPS),
            404
        );

        DB::transaction(function () use (
            $request,
            $document,
            $step
        ) {
            switch ($step) {
                case 'basic':
                    $document->update(
                        $this->editBasicData($request, $document, $this->basicData($request))
                    );
                    break;

                case 'fees':
                    // Reuse catalog validation,
                    // scoped to this section only.
                    $input = Request::create(
                        '/',
                        'POST',
                        [
                            'name' => $document->name,
                            ...$request->only('fees'),
                        ]
                    );

                    $data = $this->validateCatalogEntry(
                        $input
                    );

                    $this->syncFees(
                        $document,
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
                        $document,
                        $this->editingWizard($request)
                            ? $this->editGuideRows($document, $guide->read($request))
                            : $guide->read($request)
                    );
                    break;

                case 'offices':
                    $rows = $this->prepareOfficeLinks(
                        $request
                    );

                    if ($rows !== null) {
                        $this->syncOfficeLinks(
                            $document,
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
                $request->session()->forget('document_creation_resume');
            }

            return redirect()
                ->route(
                    'admin.documents.show',
                    $document
                )
                ->with(
                    'success',
                    $this->editingWizard($request) ? 'Document editing finished.' : 'Document creation finished.'
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
            $document,
            $step,
            $next
        );

        return redirect()
            ->route(
                $this->wizardRoutes($request, 'documents').'.show',
                [$document, $next]
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
        Document $document,
        string $step,
        string $next
    ): void {
        if ($this->editingWizard($request)) {
            $request->session()->put("document_edit_progress.{$document->id}.{$step}", true);
            return;
        }

        $request->session()->put(
            "document_creation_progress.{$document->id}.{$step}",
            true
        );

        $request->session()->put(
            'document_creation_resume',
            [
                'id' => $document->id,
                'step' => $next,
            ]
        );
    }

    private function page(
        Request $request,
        ?Document $document,
        string $step,
        ?string $creationKey = null
    ) {
        $document?->load([
            'agency',
            'requirementSets.groups.items.governmentId',
            'requirementSets.groups.items.document',
            'requirementSets.groups.ways',
            'requirementSets.applicationSteps.blocks',
            'fees',
            'offices.agency',
        ]);

        $sets = $document?->requirementSets
            ?? collect();

        $groups = $sets->flatMap->groups;

        $counts = [
            'groups' => $groups->count(),
            'items' => $groups->sum(
                fn ($group) => $group
                    ->items
                    ->count()
            ),
            'fees' => $document?->fees->count() ?? 0,
            'guide' => $sets->sum(
                fn ($set) => $set
                    ->applicationSteps
                    ->count()
            ),
            'offices' => $document?->offices->count() ?? 0,
        ];

        $resume = $request
            ->session()
            ->get('document_creation_resume');

        $resumeId = $resume
            ? Document::find(
                $resume['id']
            )
            : null;

        return view(
            'admin.government_ids.wizard.page',
            [
                'governmentId' => $document,
                'recordKind' => 'document',
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

                'completed' => $document
                    ? $request->session()->get(
                        ($this->editingWizard($request) ? "document_edit_progress" : "document_creation_progress").".{$document->id}",
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