@php
    $recordKind = $recordKind ?? 'government_id';
    $governmentId = $document ?? ($governmentId ?? null);
    $recordRoutes = $recordKind === 'document' ? 'admin.documents' : 'admin.government-ids';
    $recordLabel = $recordKind === 'document' ? 'Document' : 'ID';
    $addTitle = $recordKind === 'document' ? 'Add Document' : 'Add ID or Credential';
    $editTitle = $recordKind === 'document' ? 'Edit Document' : 'Edit ID or Credential';
    $createLabel = $recordKind === 'document' ? 'Create Document' : 'Create ID';
    $recordCategories = $recordKind === 'document'
        ? ['Civil Registry', 'Clearance', 'Certification', 'Business / Permit', 'Tax / Property', 'Record / Report']
        : ['Identity ID', 'Sector-Specific ID', 'Driving Credential', 'Professional Credential', 'Tax ID', 'Travel Document'];
    $savedCategory = old('category', $governmentId?->category);
    if (is_string($savedCategory) && filled($savedCategory) && !in_array($savedCategory, $recordCategories, true)) $recordCategories[] = $savedCategory;
@endphp
@extends('admin.layouts.app')

@section('title', $governmentId->name . ' | IDireksyon')
@section('page_title', $recordLabel.' Details')

@section('content')

@php
    $directoryQuery = request()->only([
        'q',
        'level',
        'category',
        'sort',
    ]);

    $directoryQuery['view'] = in_array(request('view'), ['grid', 'table'], true)
        ? request('view') : ($recordKind === 'document' ? 'table' : 'grid');
    $hasStructuredRequirements = $governmentId->requirementSets->contains(fn ($set) => $set->groups->isNotEmpty());
    $structuredGuideTypes = $governmentId->requirementSets
        ->filter(fn ($set) => $set->applicationSteps->isNotEmpty())
        ->pluck('application_type');
    $legacyGuideFields = collect([
        'application_process' => ['new', 'Application Process / Steps'],
        'renewal_process' => ['renewal', 'Renewal Process'],
        'replacement_process' => ['replacement', 'Replacement Process'],
    ])->filter(fn ($guide, $field) => filled($governmentId->$field) && ! $structuredGuideTypes->contains($guide[0]))
        ->map(fn ($guide) => $guide[1]);
@endphp


<style>
    .id-record { width: 100%; max-width: none; margin: 0 auto; color: #1e293b; }
    .id-record .record-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
    .id-record .record-actions { display: flex; align-items: center; gap: 10px; }
    .id-record .record-sheet { display: grid; gap: 20px; width: 100%; }
    .id-record .record-header,
    .id-record .record-sheet > .admin-panel {
        min-width: 0; margin: 0; padding: clamp(18px, 3vw, 32px);
        background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: none;
    }
    .id-record .record-header h1 { font-size: clamp(23px, 3vw, 32px); line-height: 1.25; font-weight: 700; letter-spacing: -.025em; overflow-wrap: anywhere; }
    .id-record .record-meta { margin-bottom: 8px; color: #64748b; font-size: 13px; }
    .id-record .record-sheet h2 { font-size: 18px; line-height: 1.4; font-weight: 700; margin-bottom: 16px; }
    .id-record .record-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .id-record .record-fact { min-width: 0; padding: 14px 16px; border-radius: 10px; background: #f8fafc; font-size: 14px; line-height: 1.55; }
    .id-record .record-fact dt { margin-bottom: 4px; color: #475569; font-size: 13px; }
    .id-record .record-fact dd { margin: 0; font-weight: 600; white-space: pre-line; overflow-wrap: anywhere; }
    .id-record .record-fact-wide { grid-column: 1 / -1; background: #fff; padding: 12px 0; border-top: 1px solid #e2e8f0; }
    .id-record .record-fact-wide dd { font-weight: 400; max-width: 85ch; }
    .id-record .record-offices > article { padding: 18px 0; border: 0; border-bottom: 1px solid #e2e8f0; border-radius: 0; }
    .id-record .record-offices > article:last-child { border-bottom: 0; padding-bottom: 0; }
    .id-record .record-history { color: #64748b; }
    .id-record [id^="record-"] { scroll-margin-top: 5rem; }
    .id-record .record-anchor { height: 0; margin-bottom: -20px; }
    @media (max-width: 960px) { .id-record .record-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 540px) {
        .id-record .record-facts { grid-template-columns: 1fr; }
        .id-record .record-toolbar { flex-wrap: wrap; gap: 12px; }
        .id-record .record-actions { flex-wrap: wrap; }
    }
</style>

<div class="id-record">


    {{-- SUCCESS MESSAGE --}}


    <div class="record-toolbar">
        <a href="{{ route($recordRoutes.'.index', $directoryQuery) }}" class="text-sm text-slate-600 hover:underline">← Back to directory</a>
        <div class="record-actions">
            @include('admin.catalog.partials.visibility')
            <a href="{{ route($recordRoutes.'.edit', $governmentId) }}" class="admin-primary">Edit details</a>
            @include('admin.catalog.partials.delete-modal')
        </div>
    </div>

    @if($governmentId->isHidden())
        <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status" data-hidden-banner>
            <strong>Hidden from residents</strong> since {{ $governmentId->hidden_at->copy()->timezone('Asia/Manila')->format('j M Y') }}@if($governmentId->hiddenBy) by {{ $governmentId->hiddenBy->name }}@endif.
            It does not appear anywhere in the app. You can still edit everything here; choose “Show entry” to bring it back.
        </p>
    @endif

    @error('_catalog_revision')<p role="alert" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>@enderror

    <div class="record-sheet">
        <header class="record-header flex flex-col gap-5 sm:flex-row sm:justify-between">
            <div class="min-w-0">
                @if(filled($governmentId->category) || filled($governmentId->level))
                    <p class="record-meta">{{ collect([$governmentId->category, $governmentId->level])->filter(fn ($value) => filled($value))->implode(' · ') }}</p>
                @endif
                <h1>{{ \App\Services\CatalogEncoding::displayName($governmentId) }}</h1>
                @if($recordKind === 'document' && filled($governmentId->issued_by) && $governmentId->issued_by !== $governmentId->agency?->name)
                    <p class="mt-3 break-words text-sm text-slate-600">{{ $governmentId->issued_by }}</p>
                @endif
                @if($governmentId->agency)
                    <p class="mt-3 break-words text-sm text-slate-600">{{ $governmentId->agency->name }}@if(filled($governmentId->agency->acronym)) ({{ $governmentId->agency->acronym }})@endif</p>
                @endif
                @include('admin.catalog.partials.review-dates')
            </div>
            <div class="flex shrink-0 flex-col items-start gap-2 sm:items-center">
                    @if($governmentId->image_url)
                        <img src="{{ $governmentId->image_url }}" alt="" class="h-24 w-28 rounded-lg bg-slate-100 object-contain">
                    @else
                        <div class="flex h-24 w-28 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-center text-xs text-slate-400">No image yet</div>
                    @endif
                    <a href="{{ route($recordRoutes.'.edit', $governmentId) }}" class="admin-secondary">Manage image</a>
            </div>
        </header>

        <nav aria-label="Record sections" class="flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3 text-sm" data-record-sections>
            @php
                $recordSections = array_filter([
                    'record-basics' => 'Basic information',
                    'record-requirements' => $hasStructuredRequirements || filled($governmentId->requirements) || filled($governmentId->prerequisite_notes) ? 'Requirements' : null,
                    'record-guide' => $structuredGuideTypes->isNotEmpty() ? 'Application Guide' : null,
                    'record-fees' => $governmentId->fees->isNotEmpty() || filled($governmentId->fee) || $legacyGuideFields->isNotEmpty() ? 'Fees & saved steps' : null,
                    'record-offices' => $governmentId->offices->isNotEmpty() || filled($governmentId->office_location) || filled($governmentId->office_hours) ? 'Offices' : null,
                    'record-sources' => 'Sources & request methods',
                    'record-history' => 'Edit history',
                ]);
            @endphp
            @foreach($recordSections as $section => $label)
                <a href="#{{ $section }}" class="rounded-md px-3 py-1.5 font-medium text-[#012877] hover:bg-slate-100">{{ $label }}</a>
            @endforeach
        </nav>

            <section class="admin-panel" id="record-basics">
                <h2>Basic information</h2>
                <dl class="record-facts">
                    <div class="record-fact">
                        <dt>Application Availability</dt>
                        <dd>{{ \App\Support\ApplicationAvailability::label($governmentId->application_availability) }}</dd>
                    </div>
                    <div class="record-fact">
                        <dt>Acquisition Type</dt>
                        <dd>{{ \App\Support\AcquisitionType::label($governmentId->acquisition_type) }}</dd>
                    </div>
                    @if(filled($governmentId->target_group))
                        <div class="record-fact">
                            <dt>Target Group / Qualification</dt>
                            <dd>{{ $governmentId->target_group }}</dd>
                        </div>
                    @endif
                    <div class="record-fact">
                        <dt>Guidance Mode</dt>
                        <dd>{{ \App\Support\GuidanceMode::label($governmentId->guidance_mode) }}</dd>
                    </div>
                    <div class="record-fact">
                        <dt>Include in Smart Sequencing</dt>
                        <dd>{{ $governmentId->include_in_smart_sequencing ? 'Yes' : 'No' }}</dd>
                    </div>
                    @foreach(['validity' => 'Validity Period', 'processing_time' => 'Processing Time'] as $field => $label)
                        @if(filled($governmentId->$field) && ! ($field === 'validity' && $governmentId->validity_type === 'not_applicable'))
                            <div class="record-fact">
                                <dt>{{ $label }}</dt>
                                <dd>{{ $governmentId->$field }}</dd>
                            </div>
                        @endif
                    @endforeach
                    @foreach(['description' => 'Description', 'purpose' => 'Purpose / Use', 'processing_time_note' => 'Processing time notes'] as $field => $label)
                        @if(filled($governmentId->$field))
                            <div class="record-fact record-fact-wide">
                                <dt>{{ $label }}</dt>
                                <dd>{{ $governmentId->$field }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </section>

    @if(filled($governmentId->eligibility))
        <section class="admin-panel mt-6 p-6">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Eligibility</h2>
            <p class="whitespace-pre-line break-words text-sm leading-7 text-slate-600">{{ $governmentId->eligibility }}</p>
        </section>
    @endif

    @include('admin.catalog.partials.prerequisites', ['prerequisiteOwner' => $governmentId])

    <div id="record-requirements" class="record-anchor"></div>
    @include('admin.government_ids.partials.checklists', ['editable' => false])

    {{-- REQUIREMENTS --}}
    @if(! $hasStructuredRequirements && (filled($governmentId->requirements) || filled($governmentId->prerequisite_notes)))
        <section class="admin-panel mt-6 p-6">
            <h2 class="mb-6 text-lg font-semibold text-slate-900">Requirements</h2>
            <div class="space-y-6">
                @foreach(['requirements' => 'Requirements', 'prerequisite_notes' => 'Prerequisites / Dependencies'] as $field => $label)
                    @if(filled($governmentId->$field))
                        <div>
                            <h3 class="mb-2 text-sm font-semibold text-slate-700">{{ $label }}</h3>
                            <p class="whitespace-pre-line break-words text-sm leading-7 text-slate-600">{{ $governmentId->$field }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @if($structuredGuideTypes->isNotEmpty())<div id="record-guide" class="record-anchor"></div>@endif
    @include('admin.government_ids.partials.application-guide-view')

    {{-- APPLICATION GUIDE --}}
    @if($governmentId->fees->isNotEmpty() || filled($governmentId->fee) || $legacyGuideFields->isNotEmpty())
    <section class="admin-panel mt-6 p-6" id="record-fees">

        <h2 class="mb-6 text-lg font-semibold text-slate-900">
            {{ $legacyGuideFields->isEmpty() ? 'Fees' : (($governmentId->fees->isNotEmpty() || filled($governmentId->fee)) ? 'Fees & Application Guide' : 'Application Guide') }}
        </h2>


        <div class="space-y-7">

            {{-- MULTIPLE FEES --}}
            @if($governmentId->fees->isNotEmpty() || filled($governmentId->fee))
            <div>

                <h3 class="mb-3 text-sm font-semibold text-slate-700">
                    Application Fees / Costs
                </h3>


                @if($governmentId->fees->isNotEmpty())

                    <div
                        class="overflow-hidden rounded-xl
                               border border-slate-200"
                    >

                        @foreach($governmentId->fees as $fee)
                            @if($fee->type === 'options')
                                @include('admin.government_ids.partials.fee-display')
                                @continue
                            @endif

                            <div
                                class="flex flex-col gap-3 border-b
                                       border-slate-100 px-4 py-4
                                       last:border-b-0
                                       sm:flex-row sm:items-start
                                       sm:justify-between"
                            >

                                <div class="min-w-0">

                                    <div
                                        class="flex flex-wrap
                                               items-center gap-2"
                                    >

                                        <p
                                            class="text-sm font-medium
                                                   text-slate-800"
                                        >
                                            {{ $fee->label }}
                                        </p>


                                        @if($fee->is_optional)

                                            <span
                                                class="rounded-full bg-blue-50
                                                       px-2 py-0.5
                                                       text-xs font-medium
                                                       text-blue-700"
                                            >
                                                Optional
                                            </span>

                                        @endif

                                    </div>


                                    @if(filled($fee->notes))

                                        <p
                                            class="mt-1 whitespace-pre-line
                                                   break-words text-xs
                                                   leading-5 text-slate-500"
                                        >
                                            {{ $fee->notes }}
                                        </p>

                                    @endif

                                </div>


                                <p
                                    class="shrink-0 text-sm font-semibold
                                           text-slate-900"
                                >

                                    @switch($fee->type)

                                        @case('fixed')

                                            ₱{{
                                                number_format(
                                                    (float) $fee->amount_min,
                                                    2
                                                )
                                            }}

                                            @break


                                        @case('range')

                                            ₱{{
                                                number_format(
                                                    (float) $fee->amount_min,
                                                    2
                                                )
                                            }}
                                            –
                                            ₱{{
                                                number_format(
                                                    (float) $fee->amount_max,
                                                    2
                                                )
                                            }}

                                            @break


                                        @case('free')

                                            Free

                                            @break


                                        @case('varies')

                                            Varies

                                            @break

                                    @endswitch

                                </p>

                            </div>

                        @endforeach

                    </div>


                {{-- FALLBACK FOR OLD RECORDS --}}
                @elseif(filled($governmentId->fee))

                    <p
                        class="whitespace-pre-line break-words
                               text-sm leading-7 text-slate-600"
                    >
                        {{ $governmentId->fee }}
                    </p>

                @endif

            </div>


            @endif

            @foreach($legacyGuideFields as $field => $label)
                @if(filled($governmentId->$field))
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-slate-700">{{ $label }}</h3>
                        <p class="whitespace-pre-line break-words text-sm leading-7 text-slate-600">{{ $governmentId->$field }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
    @endif

    {{-- LINKED OFFICES --}}
    <div id="record-offices" class="record-anchor"></div>
    @if($governmentId->offices->isNotEmpty())
    <section class="admin-panel mt-6 p-6">
        <h2 class="text-lg font-semibold text-slate-900">Linked Offices</h2>
        <p class="mt-1 text-sm text-slate-500">Branches researched for this {{ strtolower($recordLabel) }}. Draft and inactive branches remain hidden from residents.</p>
        <div class="record-offices">
            @foreach($governmentId->offices as $office)
                <article class="rounded-xl border border-slate-200 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ $office->name }}</h3>
                            @if(filled($office->agency?->name))
                                <p class="mt-1 text-sm text-slate-500">{{ $office->agency->name }}</p>
                            @endif
                            @php
                                $officeAddress = collect([$office->address, $office->municipality, $office->province])
                                    ->filter(fn ($value) => filled($value))
                                    ->implode(', ');
                            @endphp
                            @if(filled($officeAddress))
                                <p class="mt-1 text-sm text-slate-500">{{ $officeAddress }}</p>
                            @endif
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">{{ ucfirst($office->status) }}</span>
                    </div>
                    @php
                        $services = collect(['new_application_status' => 'New Application', 'renewal_status' => 'Renewal', 'replacement_status' => 'Replacement'])
                            ->filter(fn ($label, $field) => in_array($office->pivot->$field, ['available', 'unavailable'], true));
                        $schedules = $office->schedules->filter(fn ($schedule) => $schedule->status === 'closed' || ($schedule->status === 'open' && $schedule->intervals->isNotEmpty()));
                        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    @endphp
                    @if($services->isNotEmpty())
                        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                            @foreach($services as $field => $label)
                                <div>
                                    <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                    <dd class="mt-1 text-sm font-medium text-slate-800">{{ $office->pivot->$field === 'available' ? 'Available' : 'Not available' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                    @if($schedules->isNotEmpty())
                        <div class="mt-4">
                            <h4 class="mb-2 text-sm font-semibold text-slate-700">Office Hours</h4>
                            <dl class="space-y-2 text-sm">
                                @foreach($schedules as $schedule)
                                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                                        <dt class="w-24 font-medium text-slate-700">{{ $dayNames[$schedule->day_of_week] }}</dt>
                                        <dd class="text-slate-600">
                                            @if($schedule->status === 'closed')
                                                Closed
                                            @else
                                                @foreach($schedule->intervals as $interval)
                                                    {{ \Carbon\Carbon::createFromFormat('H:i', substr($interval->opens_at, 0, 5))->format('g:i A') }}–{{ \Carbon\Carbon::createFromFormat('H:i', substr($interval->closes_at, 0, 5))->format('g:i A') }}@unless($loop->last), @endunless
                                                @endforeach
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                    @if(filled($office->pivot->service_notes))
                        <p class="mt-4 whitespace-pre-line break-words text-sm text-slate-600">{{ $office->pivot->service_notes }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-4 text-sm">
                        <a href="{{ route('admin.offices.edit', $office) }}" class="font-medium text-[#012877] hover:underline">Office details & hours</a>
                        @if($office->pivot->source_url && in_array(strtolower(parse_url($office->pivot->source_url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true))
                            <a href="{{ $office->pivot->source_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-[#012877] hover:underline">Service source ↗</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
    @endif

    {{-- LEGACY OFFICE INFORMATION --}}
    @if($governmentId->offices->isEmpty() && (filled($governmentId->office_location) || filled($governmentId->office_hours)))
        <section class="admin-panel mt-6 p-6">
            <h2 class="mb-6 text-lg font-semibold text-slate-900">Office Information</h2>
            <div class="grid gap-6 md:grid-cols-2">
                @foreach(['office_location' => 'Government Office / Location', 'office_hours' => 'Office Hours'] as $field => $label)
                    @if(filled($governmentId->$field))
                        <div>
                            <h3 class="mb-2 text-sm font-semibold text-slate-700">{{ $label }}</h3>
                            <p class="whitespace-pre-line break-words text-sm leading-7 text-slate-600">{{ $governmentId->$field }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @include('admin.catalog.partials.encoding-summary')

    <section class="admin-panel record-history" id="record-history">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Edit History</h2>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt data-checklist-history="editor_label" class="text-sm text-slate-500">{{ $lastEdit?->action === 'created' ? 'Created by' : 'Last edited by' }}</dt>
                <dd data-checklist-history="editor" class="mt-1 font-medium text-slate-800">{{ $lastEdit?->user?->name ?? 'Editor not recorded' }}</dd>
            </div>
            <div>
                <dt data-checklist-history="date_label" class="text-sm text-slate-500">{{ $lastEdit?->action === 'created' ? 'Created on' : 'Last edited' }}</dt>
                <dd data-checklist-history="date" class="mt-1 text-sm text-slate-700">
                    @php($editTime = $lastEdit?->created_at ?? $governmentId->updated_at)
                    {{ $editTime ? $editTime->copy()->timezone('Asia/Manila')->format('F j, Y · g:i A').' PHT' : 'Not recorded' }}
                </dd>
            </div>
        </dl>
        @if(! $lastEdit)
            <p data-checklist-history="fallback" class="mt-3 text-xs text-slate-500">No recorded CMS edit history is available. The date above is the record’s last update time.</p>
        @endif
    </section>
    </div>
</div>
@endsection
