@extends('admin.layouts.app')

@section('title', 'Add Government ID | IDireksyon')
@section('page_title', 'Add Government ID')

@section('content')
@include('admin.government_ids.partials.editor-styles')

@php
    $stepKeys = array_keys($steps);
    $position = array_search($step, $stepKeys, true);
    $previous = $position > 0 ? $stepKeys[$position - 1] : null;

    $value = function ($field, $default = '') use ($governmentId) {
        $input = old($field, $governmentId?->$field ?? $default);

        return is_scalar($input) ? (string) $input : '';
    };
@endphp

<div class="id-editor mx-auto max-w-6xl space-y-5">

    {{-- TOP HEADER --}}
    <header class="space-y-4">
        <a
            href="{{ route('admin.government-ids.index') }}"
            class="inline-flex items-center text-sm text-slate-500 transition hover:text-slate-800"
        >
            ← Back to directory
        </a>

        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
                Add Government ID
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
                    You have a saved Government ID creation in progress.
                </p>
            </div>

            <a
                class="admin-secondary"
                href="{{ route('admin.government-ids.wizard.show', [$resume['record'], $resume['step']]) }}"
            >
                Resume
            </a>
        </aside>
    @endif


    {{-- COMPACT WIZARD NAVIGATION --}}
    <nav
        aria-label="Government ID creation steps"
        class="overflow-x-auto border-b border-slate-200"
    >
        <ol class="flex min-w-max gap-1">
            @foreach($steps as $key => $label)
                <li>
                    @if($governmentId)
                        <a
                            href="{{ route('admin.government-ids.wizard.show', [$governmentId, $key]) }}"
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


    {{-- CURRENT SECTION --}}
    <div class="pb-1 pt-2">
        <h2 class="text-2xl font-semibold tracking-tight text-slate-900">
            {{ $step === 'basic' ? 'Basic Information' : $steps[$step] }}
        </h2>

        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
            @switch($step)
                @case('basic')
                    Add the main information about this ID.
                    @break

                @case('requirements')
                    Add the documents and other requirements needed to apply for this ID.
                    @break

                @case('fees')
                    Add the fees applicants may need to pay.
                    @break

                @case('guide')
                    Build the application process step by step.
                    @break

                @case('offices')
                    Choose where applicants can process this ID.
                    @break

                @case('review')
                    Review the saved information before finishing.
                    @break
            @endswitch
        </p>
    </div>


    {{-- MAIN STEP FORM --}}
    <form
        method="POST"
        action="{{ $governmentId
            ? route('admin.government-ids.wizard.save', [$governmentId, $step])
            : route('admin.government-ids.wizard.store') }}"
        class="space-y-6"
    >
        @csrf

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

                @include(
                    'admin.government_ids.partials.checklists',
                    [
                        'editable' => true,
                        'deferred' => false,
                        'wizardMode' => true
                    ]
                )

                @break


            {{-- FEES --}}
            @case('fees')

                <section class="admin-panel p-6">
                    @include(
                        'admin.government_ids.partials.fee-items-field',
                        [
                            'selectedFees' => $governmentId
                                ->fees
                                ->sortBy('sort_order')
                        ]
                    )
                </section>

                @break


            {{-- APPLICATION GUIDE --}}
            @case('guide')

                @if($requirementsIncomplete)
                    <div
                        role="status"
                        class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900"
                    >
                        Requirements are not complete yet, but you can still
                        continue building the application guide.
                    </div>
                @endif

@php
    $guideRows = array_values(array_filter(
        app(\App\Services\GovernmentIdApplicationGuide::class)->present($governmentId),
        fn ($row) => $row['application_type'] === 'new' && $row['applicant_type'] === 'all'
    ));
    $guideRows = array_slice($guideRows, 0, 1);
    $guideConfig = [
        'deferred' => $deferred ?? false,
        'scenarios' => $guideRows,
        'oldPayload' => is_string(old('application_guide_payload')) ? old('application_guide_payload') : null,
    ];
@endphp
<section class="admin-panel p-6" aria-label="Application Guide" x-data="governmentIdGuide(@js($guideConfig))" @guide-scenario-changed.window="scenarioChanged($event.detail)">
    <input type="hidden" x-ref="guidePayload" name="application_guide_payload" :disabled="!ready" :value="payload" disabled>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="text-lg font-semibold text-slate-900">Application Guide</h2><p class="mt-1 text-sm text-slate-500">Explain what the applicant should do, one step at a time.</p></div>
        <span class="text-xs text-slate-500">Saved with the main form</span>
    </div>
    <noscript><p class="mt-3 text-sm text-amber-700">Enable JavaScript to edit the Application Guide. Existing steps will be preserved.</p></noscript>
    @foreach($errors->getMessages() as $field => $messages)
        @if(str_starts_with($field, 'application_guide'))
            @foreach($messages as $message)<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@endforeach
        @endif
    @endforeach
    <p x-ref="guideError" tabindex="-1" x-show="error" x-text="error" class="mt-3 text-sm text-red-600" role="alert"></p>
    <template x-if="current">
        <div class="mt-5 space-y-4">
            <p id="guide-reorder-help" class="text-xs text-slate-500">Drag a card to reorder. With its header focused, press Alt + ↑ or ↓. Press Escape to cancel dragging.</p>
            <p x-show="!current.steps.length && !draft" class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">Start with the first thing this applicant needs to do.</p>
            <template x-for="(step, index) in current.steps" :key="step.key">
                <article :data-guide-card="step.key" class="relative min-w-0 rounded-xl border border-slate-200 bg-white"
                    :class="{ 'opacity-50 ring-2 ring-blue-400': stepDrag?.active && stepDrag.key === step.key, 'border-t-4 border-t-blue-600': stepDrag?.active && stepDrag.target === step.key && stepDrag.key !== step.key && !stepDrag.after, 'border-b-4 border-b-blue-600': stepDrag?.active && stepDrag.target === step.key && stepDrag.key !== step.key && stepDrag.after }">
                    <div tabindex="0" role="group" :aria-label="'Step ' + (index + 1) + ': ' + step.title" aria-roledescription="reorderable step" aria-describedby="guide-reorder-help"
                        :data-guide-header="step.key" class="flex select-none items-center gap-3 rounded-xl p-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700" style="touch-action: none;"
                        :class="stepDrag?.key === step.key ? 'cursor-grabbing' : 'cursor-grab'"
                        @pointerdown="startStepDrag($event, step.key)" @pointermove="moveStepDrag($event)" @pointerup="endStepDrag($event)"
                        @pointercancel="cancelStepDrag()" @lostpointercapture="cancelStepDrag()" @keydown="cardKeydown($event, index)" @click="finishCardClick($event)">
                        <span aria-hidden="true" class="shrink-0 text-lg text-slate-400">⋮⋮</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="break-words text-sm font-semibold text-slate-900"><span x-text="(index + 1) + '. '"></span><span x-text="step.title"></span></h3>
                            <p class="mt-1 truncate text-sm text-slate-500" x-text="summary(step)"></p>
                        </div>
                        <button type="button" class="guide-tool flex min-h-10 min-w-10 shrink-0 items-center justify-center" @click.stop="draft && stepIndex === index ? applyStep() : editStep(index)"
                            :disabled="!!stepDrag || (!!draft && stepIndex !== index)" :aria-label="(draft && stepIndex === index ? 'Save and collapse step ' : 'Expand step ') + (index + 1)" :aria-expanded="!!draft && stepIndex === index"
                            :aria-controls="'guide-step-' + step.key" :data-guide-step="step.key"><span aria-hidden="true" x-text="draft && stepIndex === index ? '⌃' : '⌄'"></span></button>
                        <div class="relative shrink-0" @click.outside="if (openMenuKey === step.key) closeMenu(false)" @keydown.escape.stop.prevent="closeMenu()">
                            <button type="button" class="guide-tool flex min-h-10 min-w-10 items-center justify-center text-xl" @click.stop="toggleMenu(step.key)" :disabled="!!stepDrag"
                                :aria-label="'Actions for step ' + (index + 1)" aria-haspopup="menu" :aria-expanded="openMenuKey === step.key" :aria-controls="'guide-menu-' + step.key" :data-step-menu-trigger="step.key"><span aria-hidden="true">⋮</span></button>
                            <div x-show="openMenuKey === step.key" x-cloak :id="'guide-menu-' + step.key" :data-step-menu="step.key" role="menu" :aria-label="'Actions for step ' + (index + 1)"
                                class="absolute right-0 top-full z-20 mt-1 w-36 rounded-lg border border-slate-200 bg-white p-1 shadow-lg" @keydown="menuKeydown($event)">
                                <button type="button" role="menuitem" class="guide-tool block w-full p-2 text-left" :disabled="!!draft && stepIndex !== index" @click.stop="closeMenu(false); editMenuStep(index)">Edit</button>
                                <button type="button" role="menuitem" class="guide-tool block w-full p-2 text-left text-red-700" :disabled="!!draft" @click.stop="closeMenu(); removeStep(index)">Delete</button>
                            </div>
                        </div>
                    </div>
                    <div :id="'guide-step-' + step.key" class="px-4" :class="draft && stepIndex === index ? 'pb-4' : ''">
                        <template x-if="draft && stepIndex === index"><div>@include('admin.government_ids.partials.application-guide-step-editor')</div></template>
                    </div>
                </article>
            </template>
            <template x-if="draft && stepIndex === -1">
                <article class="min-w-0 rounded-xl border border-blue-400 p-4">
                    <h3 class="mb-4 font-semibold" x-text="'Step ' + (current.steps.length + 1) + '. New application step'"></h3>
                    @include('admin.government_ids.partials.application-guide-step-editor')
                </article>
            </template>
            <button type="button" x-ref="addStepButton" class="admin-secondary" @click="editStep()" :disabled="!!draft || !!stepDrag || addingScenario || current.steps.length >= 100">+ Add Application Step</button>
            <p class="sr-only" role="status" aria-live="polite" x-text="dragStatus"></p>
            <p x-show="stepDrag?.active" class="text-sm font-medium text-blue-800">Dragging — release at the highlighted edge. Press Escape to cancel.</p>
            <p x-show="current.dirty || draft" class="text-sm text-amber-700" role="status">Unsaved guide changes — save the main entry to keep them.</p>
            <p class="text-xs text-slate-500">Step changes, deletions, and order are saved with the main form. Saving Requirements separately does not save this guide.</p>
        </div>
    </template>
</section>


                @break


            {{-- OFFICES --}}
            @case('offices')

                @include(
                    'admin.government_ids.partials.office-links-field',
                    [
                        'selectedOffices' => $governmentId->offices,
                        'officeModal' => false
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
                            href="{{ route('admin.government-ids.wizard.show', [$governmentId, $previous]) }}"
                        >
                            ← Back
                        </a>
                    @endif
                </div>


                <div class="flex items-center gap-4">

                    @if($step === 'basic' && !$governmentId)
                        <p class="hidden max-w-sm text-right text-xs leading-5 text-slate-400 md:block">
                            Saving creates this ID immediately.
                        </p>
                    @endif

                    <button
                        type="submit"
                        class="admin-primary"
                    >
                        @if($step === 'review')
                            Finish
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

</div>

@endsection
