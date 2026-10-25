@extends('admin.layouts.app')

@section('title', 'Add Government ID | IDireksyon')
@section('page_title', 'Add Government ID')

@section('content')
@include('admin.government_ids.partials.editor-styles')
@php
    $position = array_search($step, array_keys($steps), true);
    $previous = $position > 0 ? array_keys($steps)[$position - 1] : null;
    $value = function ($field, $default = '') use ($governmentId) {
        $input = old($field, $governmentId?->$field ?? $default);
        return is_scalar($input) ? (string) $input : '';
    };
@endphp
<div class="id-editor mx-auto max-w-6xl space-y-6">
    <header class="editor-heading">
        <a href="{{ route('admin.government-ids.index') }}" class="text-sm text-slate-600 hover:underline">← Back to directory</a>
        <h1>{{ $governmentId?->name ?? 'Add Government ID' }}</h1>
        <p class="mt-2 text-sm text-slate-600">One section at a time. Only Basic Information is required; you can leave the other sections empty.</p>
    </header>

    @if(!$governmentId && $resume)
        <aside class="admin-panel p-4">
            <p class="text-sm">Continue adding details to <strong>{{ $resume['record']->name }}</strong>?</p>
            <a class="admin-secondary mt-3" href="{{ route('admin.government-ids.wizard.show', [$resume['record'], $resume['step']]) }}">Resume saved creation</a>
        </aside>
    @endif

    <nav aria-label="Government ID creation steps" class="admin-panel overflow-x-auto p-4">
        <ol class="flex min-w-max gap-3">
            @foreach($steps as $key => $label)
                <li class="flex items-center gap-3">
                    @if(!$loop->first)<span aria-hidden="true" class="text-slate-400">→</span>@endif
                    @if($governmentId)
                        <a href="{{ route('admin.government-ids.wizard.show', [$governmentId, $key]) }}"
                           @if($key === $step) aria-current="step" @endif
                           class="rounded-lg px-3 py-2 text-sm {{ $key === $step ? 'bg-[#012877] font-semibold text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                            {{ isset($completed[$key]) && $key !== $step ? '✓ ' : ($loop->iteration.'. ') }}{{ $label }}
                        </a>
                    @else
                        <span @if($key === $step) aria-current="step" @endif class="rounded-lg px-3 py-2 text-sm {{ $key === $step ? 'bg-[#012877] font-semibold text-white' : 'text-slate-400' }}">{{ $loop->iteration }}. {{ $label }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
        <p class="mt-3 text-xs text-slate-500">Step {{ $position + 1 }} of 7 · Checkmarks mean you saved or continued past that section, including empty sections.</p>
    </nav>

    @if($errors->any())
        <div role="alert" tabindex="-1" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Please check these fields before continuing:</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div>
        <h2 class="text-xl font-semibold">{{ $step === 'basic' ? 'Basic Information' : $steps[$step] }}</h2>
        <p class="mt-1 text-sm text-slate-600">
            @switch($step)
                @case('basic') Start with the ID name and the details you already know. @break
                @case('cases') Who is this information for? Add the applicant and application cases you need. @break
                @case('requirements') What does this applicant need to submit or satisfy? Choose a case using Edit checklist. @break
                @case('fees') What will applicants pay? Fees apply to the whole ID, across all applicant cases. @break
                @case('guide') What should applicants do, one step at a time? @break
                @case('offices') Where can applicants process this ID? Link existing office records below. @break
                @case('review') Check the saved information. You can return to any section before finishing. @break
            @endswitch
        </p>
    </div>

    @if($step === 'cases')
        @include('admin.government_ids.wizard.cases')
    @endif

    <form method="POST" action="{{ $governmentId ? route('admin.government-ids.wizard.save', [$governmentId, $step]) : route('admin.government-ids.wizard.store') }}" class="space-y-6">
        @csrf
        @if(!$governmentId)<input type="hidden" name="creation_key" value="{{ old('creation_key', $creationKey) }}">@endif
        @switch($step)
            @case('basic')
                @include('admin.government_ids.wizard.basic')
                @break
            @case('requirements')
                @if(!$counts['cases'])
                    <p class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">No applicant cases added yet. Return to Applicant Cases, or add a checklist below to create a case. You can also continue without requirements.</p>
                @endif
                <p class="text-sm text-slate-600">Save each checklist before continuing. Checklist saves go directly to this ID.</p>
                @include('admin.government_ids.partials.checklists', ['editable' => true, 'deferred' => false])
                @break
            @case('fees')
                @if(!$counts['fees'])<p class="text-sm text-slate-500">Not added yet. You can continue without fees.</p>@endif
                <section class="admin-panel p-6">
                    @include('admin.government_ids.partials.fee-items-field', ['selectedFees' => $governmentId->fees->sortBy('sort_order')])
                </section>
                @break
            @case('guide')
                @if($requirementsIncomplete)
                    <p role="status" class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">Requirements are not complete yet. You can still add application steps.</p>
                @endif
                @if(!$counts['guide'])<p class="text-sm text-slate-500">Not added yet. You can continue without application steps.</p>@endif
                @include('admin.government_ids.partials.application-guide-editor', ['deferred' => false])
                @break
            @case('offices')
                @if(!$counts['offices'])<p role="status" class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">No offices linked yet. You can continue and add them later.</p>@endif
                @include('admin.government_ids.partials.office-links-field', ['selectedOffices' => $governmentId->offices, 'officeModal' => false])
                @break
            @case('review')
                @include('admin.government_ids.wizard.review')
                @break
        @endswitch

        <footer class="sticky bottom-0 z-20 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white p-4">
            @if($previous)
                <a class="admin-secondary" href="{{ route('admin.government-ids.wizard.show', [$governmentId, $previous]) }}">← Back</a>
            @endif
            @if($step === 'basic' && !$governmentId)
                <p class="max-w-md text-xs text-slate-500">Saving creates this ID immediately. There is no draft/publish status, so incomplete entries may appear in the resident directory.</p>
            @else
                <p class="max-w-md text-xs text-slate-500">{{ $step === 'requirements' ? 'Requirements save with Save checklist. Continue opens the next section.' : ($step === 'review' ? 'Finish opens the existing ID details page.' : 'Save & Continue keeps this section in the database. Save before leaving this page.') }}</p>
            @endif
            <button type="submit" class="admin-primary">{{ $step === 'review' ? 'Finish' : (in_array($step, ['cases', 'requirements']) ? 'Continue →' : 'Save & Continue') }}</button>
        </footer>
    </form>
    @if($governmentId)
        <p class="text-xs text-slate-500">Saved content stays on this ID if you leave. Bookmark this page to return to the guided flow.</p>
    @endif
</div>
@endsection
