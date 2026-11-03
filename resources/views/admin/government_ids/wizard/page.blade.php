@php
    $recordKind = $recordKind ?? 'government_id';
    $recordRoutes = $recordKind === 'document' ? 'admin.documents' : 'admin.government-ids';
    $editing = $editing ?? false;
    $wizardRoutes = $recordRoutes.($editing ? '.edit-wizard' : '.wizard');
    $wizardTitle = ($editing ? 'Edit ' : 'Add ').($recordKind === 'document' ? 'Document' : 'Government ID');
    $wizardLabel = $recordKind === 'document' ? 'Document' : 'Government ID';
    $itemLabel = $recordKind === 'document' ? 'document' : 'ID';
@endphp
@extends('admin.layouts.app')

@section('title', $wizardTitle.' | IDireksyon')
@section('page_title', $wizardTitle)

@section('content')
@include('admin.government_ids.partials.editor-styles')

@php
    $stepKeys = array_keys($steps);
    $position = array_search($step, $stepKeys, true);
    $previous = $position > 0 ? $stepKeys[$position - 1] : null;
    $activePath = $governmentId?->requirementSets->firstWhere('id', $selectedPath ?? null);

    $value = function ($field, $default = '') use ($governmentId) {
        $input = old($field, $governmentId?->$field ?? $default);

        return is_scalar($input) ? (string) $input : '';
    };
@endphp

<div class="id-editor{{ $recordKind === 'document' ? ' document-editor' : '' }} mx-auto max-w-6xl space-y-5">

    {{-- TOP HEADER --}}
    <header class="space-y-4">
        <a
            href="{{ route($recordRoutes.'.index') }}"
            class="inline-flex items-center text-sm text-slate-500 transition hover:text-slate-800"
        >
            ← Back to directory
        </a>

        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
                {{ $wizardTitle }}
            </h1>

            @if($governmentId)
                <p class="mt-1 text-sm text-slate-500">
                    {{ $governmentId->name }}
                </p>
            @endif
        </div>
    </header>


    {{-- RESUME EXISTING CREATION --}}
    @if(!$governmentId && $resume)
        <aside
            class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4"
        >
            <div>
                <p class="text-sm font-medium text-slate-900">
                    Continue {{ $resume['record']->name }}?
                </p>

                <p class="mt-1 text-xs text-slate-500">
                    You have a saved {{ $wizardLabel }} creation in progress.
                </p>
            </div>

            <a
                class="admin-secondary"
                href="{{ route($recordRoutes.'.wizard.show', [$resume['record'], $resume['step']]) }}"
            >
                Resume
            </a>
        </aside>
    @endif


    {{-- COMPACT WIZARD NAVIGATION --}}
    <nav
        aria-label="{{ $wizardLabel }} {{ $editing ? 'editing' : 'creation' }} steps"
        class="overflow-x-auto border-b border-slate-200"
    >
        <ol class="flex min-w-max gap-1">
            @foreach($steps as $key => $label)
                <li>
                    @if($governmentId)
                        <a
                            href="{{ route($wizardRoutes.'.show', [$governmentId, $key, 'path' => $selectedPath ?? null]) }}"
                            @if($key === $step) aria-current="step" @endif
                            class="
                                inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm transition
                                {{ $key === $step
                                    ? 'border-[#012877] font-semibold text-[#012877]'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                                }}
                            "
                        >
                            @if(isset($completed[$key]) && $key !== $step)
                                <span
                                    class="text-xs text-slate-400"
                                    aria-hidden="true"
                                >
                                    ✓
                                </span>
                            @else
                                <span
                                    class="{{ $key === $step ? 'text-[#012877]' : 'text-slate-400' }} text-xs"
                                    aria-hidden="true"
                                >
                                    {{ $loop->iteration }}
                                </span>
                            @endif

                            <span>{{ $label }}</span>
                        </a>
                    @else
                        <span
                            @if($key === $step) aria-current="step" @endif
                            class="
                                inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm
                                {{ $key === $step
                                    ? 'border-[#012877] font-semibold text-[#012877]'
                                    : 'border-transparent text-slate-400'
                                }}
                            "
                        >
                            <span class="text-xs">
                                {{ $loop->iteration }}
                            </span>

                            <span>{{ $label }}</span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>


    {{-- VALIDATION ERRORS --}}
    @if($errors->any())
        <div
            role="alert"
            tabindex="-1"
            class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800"
        >
            <p class="font-semibold">
                Please check the highlighted information.
            </p>

            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    @if($step !== 'channels')
    {{-- CURRENT SECTION --}}
    <div class="pb-1 pt-2">
        <h2 class="text-2xl font-semibold tracking-tight text-slate-900">
            {{ $step === 'basic' ? 'Basic Information' : $steps[$step] }}
        </h2>

        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
            @if($governmentId && in_array($step, ['requirements', 'fees', 'guide', 'offices']))
            @include('admin.catalog.partials.section-visibility', ['visibilitySection' => $step, 'visibilityLabel' => $steps[$step]])
        @endif

        @switch($step)
                @case('basic')
                    Add the main information about this {{ $itemLabel }}.
                    @break

                @case('channels')
                    Set up the ways residents can request this {{ $itemLabel }}.
                    @break

                @case('requirements')
                    Add the documents and other requirements needed to apply for this {{ $itemLabel }}.
                    @break

                @case('fees')
                    Add the fees applicants may need to pay.
                    @break

                @case('guide')
                    Build the application process step by step.
                    @break

                @case('offices')
                    Choose where applicants can process this {{ $itemLabel }}.
                    @break

                @case('review')
                    {{ $editing ? 'Review changes to the saved information before finishing.' : 'Review the saved information before finishing.' }}
                    @break
            @endswitch
        </p>
    </div>


    @endif

    @if($governmentId && $step === 'channels')
        @include('admin.catalog.partials.channels')
    @elseif($governmentId && in_array($step, ['requirements', 'fees', 'guide']))
        @include('admin.catalog.partials.channel-context')
    @endif
    {{-- MAIN STEP FORM --}}
    <form
        id="catalog-step-form"
        method="POST"
        action="{{ $governmentId
            ? route($wizardRoutes.'.save', [$governmentId, $step])
            : route($wizardRoutes.'.store') }}"
        class="space-y-6"
        @if($step === 'guide')
            novalidate
            x-data="{ guideSaving: false }"
            @guide-saving.window="guideSaving = $event.detail"
        @elseif(in_array($step, ['fees', 'offices']))
            x-data="catalogStepChanges"
        @endif
    >
        @csrf
        @if($step === 'fees')<input type="hidden" name="option_content" value="1">@endif
        @if($step !== 'offices' && ($selectedPath ?? null))<input type="hidden" name="path" value="{{ $selectedPath }}">@endif
        @if($governmentId)<input type="hidden" name="_catalog_revision" value="{{ old('_catalog_revision', \App\Services\CatalogRevision::token($governmentId)) }}">@endif

        @if(!$governmentId)
            <input
                type="hidden"
                name="creation_key"
                value="{{ old('creation_key', $creationKey) }}"
            >
        @endif


        @switch($step)

            {{-- BASIC INFORMATION --}}
            @case('basic')
                @include('admin.government_ids.wizard.basic')
                @break


            {{-- REQUIREMENTS --}}
            @case('requirements')

                @include('admin.catalog.partials.prerequisites-editor')
                @include(
                    'admin.government_ids.partials.checklists',
                    [
                        'editable' => true,
                        'deferred' => false,
                        'wizardMode' => true,
                        'allowWizardCreate' => false,
                        'wizardScenarioSelector' => false
                    ]
                )

                @break


            {{-- FEES --}}
            @case('fees')

                <section class="admin-panel p-6">
                    @include(
                        'admin.government_ids.partials.fee-items-field',
                        [
                            'selectedFees' => $governmentId->fees->filter(fn ($fee) => !isset($activePath->request_channel['fee_ids']) || in_array($fee->id, $activePath->request_channel['fee_ids'], true))->sortBy('sort_order')
                        ]
                    )
                </section>

                @break


            {{-- APPLICATION GUIDE --}}
            @case('guide')

@php
    $guideRows = app(\App\Services\GovernmentIdApplicationGuide::class)->present($governmentId);
    if ($selectedPath ?? null) $guideRows = array_values(array_filter($guideRows, fn ($row) => $row['id'] === $selectedPath));
    if (!$guideRows) {
        $guideRows = [['id' => null, 'application_type' => 'new', 'applicant_type' => 'all', 'steps' => []]];
    }
    $guideConfig = [
        'deferred' => $deferred ?? false,
        'wizard' => true,
        'nextUrl' => route($wizardRoutes.'.show', [$governmentId, 'offices', 'path' => $selectedPath ?? null]),
        'stepTypes' => \App\Models\GovernmentIdApplicationStep::TYPES,
        'requirementsByScenario' => $governmentId->requirementSets->mapWithKeys(fn ($set) => [$set->id => $set->groups->count()])->all(),
        'previewReferences' => [
            'requirements' => $governmentId->requirementSets->firstWhere('id', $guideRows[0]['id'] ?? null)?->groups->count() ?? 0,
            'fees' => $governmentId->fees->filter(fn ($fee) => !isset($activePath->request_channel['fee_ids']) || in_array($fee->id, $activePath->request_channel['fee_ids'], true))->count(),
            'offices' => $governmentId->offices->filter(fn ($office) => !isset($activePath->request_channel['office_ids']) || in_array($office->id, $activePath->request_channel['office_ids'], true))->count(),
            'processingTime' => $activePath?->request_channel['processing'] ?? $governmentId->processing_time,
            'officeAction' => $governmentId->agency?->acronym
                ? 'Find a '.$governmentId->agency->acronym.' office →'
                : 'Find an office →',
        ],
        'scenarios' => $guideRows,
        'oldPayload' => is_string(old('application_guide_payload')) ? old('application_guide_payload') : null,
    ];
@endphp
@if($activePath)
@include('admin.catalog.partials.guide-editor')
@endif


                @break


            {{-- OFFICES --}}
            @case('offices')

                @include(
                    'admin.government_ids.partials.office-links-field',
                    [
                        'selectedOffices' => $governmentId->offices,
                        'officeModal' => true
                    ]
                )

                @break


            {{-- REVIEW --}}
            @case('review')

                @include('admin.government_ids.wizard.review')

                @break

        @endswitch


        {{-- BOTTOM ACTION BAR --}}
        <footer
            class="sticky bottom-0 z-20 mt-8 rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">

                <div>
                    @if($previous)
                        <a
                            class="admin-secondary"
                            href="{{ route($wizardRoutes.'.show', [$governmentId, $previous, 'path' => $selectedPath ?? null]) }}"
                        >
                            ← Back
                        </a>
                    @endif
                </div>


                <div class="flex items-center gap-4">

                    @if($step === 'basic' && !$governmentId)
                        <p class="hidden max-w-sm text-right text-xs leading-5 text-slate-400 md:block">
                            Saving creates this {{ $itemLabel }} immediately.
                        </p>
                    @endif

                    <button
                        type="submit"
                        class="admin-primary"
                        @if($step === 'guide') :disabled="guideSaving" :aria-busy="guideSaving" @endif
                    >
                        @if($step === 'guide')
                            <span x-text="guideSaving ? 'Saving…' : 'Continue'">Continue</span>
                        @elseif($step === 'review')
                            {{ $editing ? 'Finish Editing' : 'Finish' }}
                        @elseif($step === 'requirements')
                            Continue →
                        @else
                            Save & Continue
                        @endif
                    </button>

                </div>
            </div>
        </footer>

    </form>
    @if($governmentId && $step === 'guide')
        @include('admin.catalog.partials.special-cases')
    @endif

</div>

@endsection
