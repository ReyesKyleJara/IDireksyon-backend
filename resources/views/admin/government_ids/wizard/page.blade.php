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

                @case('cases')
                    Define who is applying and the type of application.
                    @break

                @case('requirements')
                    Add what each applicant case needs to submit or satisfy.
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


    {{-- APPLICANT CASES HAS ITS OWN SAVE FORMS --}}
    @if($step === 'cases')
        @include('admin.government_ids.wizard.cases')
    @endif


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

                @if(!$counts['cases'])
                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900"
                    >
                        Add an Applicant Case first if you want requirements
                        to apply to a specific applicant or application type.
                    </div>
                @endif

                @include(
                    'admin.government_ids.partials.checklists',
                    [
                        'editable' => true,
                        'deferred' => false
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

                @include(
                    'admin.government_ids.partials.application-guide-editor',
                    ['deferred' => false]
                )

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
                        @elseif(in_array($step, ['cases', 'requirements']))
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