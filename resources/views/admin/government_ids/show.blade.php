@extends('admin.layouts.app')

@section('title', $governmentId->name . ' | IDireksyon')
@section('page_title', 'ID Details')

@section('content')

@php
    $directoryQuery = request()->only([
        'q',
        'level',
        'category',
        'sort',
    ]);

    $directoryQuery['view'] =
        request('view') === 'table'
            ? 'table'
            : 'grid';
@endphp


<div class="mx-auto max-w-5xl">

    {{-- SUCCESS MESSAGE --}}
    @if(session('success'))
        <div
            class="mb-6 rounded-xl border border-emerald-200
                   bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            {{ session('success') }}
        </div>
    @endif


    {{-- HEADER ACTIONS --}}
    <div class="mb-6 flex items-center justify-between gap-4">

        <a
            href="{{ route('admin.government-ids.index', $directoryQuery) }}"
            class="admin-secondary"
        >
            ← Back to directory
        </a>


        <details class="relative">

            <summary
                aria-label="Actions for this ID"
                class="admin-secondary cursor-pointer list-none text-xl"
            >
                ⋮
            </summary>

            <div
                class="absolute right-0 z-40 mt-2 w-44 rounded-xl
                       border border-slate-200 bg-white p-2 shadow-xl"
            >

                <a
                    href="{{ route('admin.government-ids.edit', $governmentId) }}"
                    class="block rounded-lg px-4 py-3 text-sm hover:bg-slate-50"
                >
                    Edit
                </a>


                <form
                    method="POST"
                    action="{{ route('admin.government-ids.destroy', $governmentId) }}"
                    onsubmit="return confirm(
                        'Delete this ID or credential? This cannot be undone.'
                    );"
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="block w-full rounded-lg px-4 py-3
                               text-left text-sm text-red-600 hover:bg-red-50"
                    >
                        Delete
                    </button>

                </form>

            </div>

        </details>

    </div>


    {{-- MAIN ID HEADER --}}
    <article class="admin-panel overflow-hidden">

        <div class="p-6 sm:p-8">

            @if(filled($governmentId->category) || filled($governmentId->level))
                <p class="admin-eyebrow mb-3">{{ collect([$governmentId->category, $governmentId->level])->filter(fn ($value) => filled($value))->implode(' | ') }}</p>
            @endif


            <h1
                class="break-words text-3xl font-bold
                       tracking-tight text-slate-900"
            >
                {{ $governmentId->name }}
            </h1>


            @if($governmentId->agency)
                <p class="mt-3 break-words text-sm text-slate-500">
                    Issued by: <span class="font-medium text-slate-700">{{ $governmentId->agency->name }}@if(filled($governmentId->agency->acronym)) ({{ $governmentId->agency->acronym }})@endif</span>
                </p>
            @endif


            @if($governmentId->agency?->official_website)

                <a
                    href="{{ $governmentId->agency->official_website }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-2 inline-block text-sm font-medium
                           text-[#012877] hover:underline"
                >
                    Agency Website
                </a>

            @endif

        </div>

    </article>


    {{-- BASIC INFORMATION --}}
    @if(filled($governmentId->purpose) || filled($governmentId->description) || ($governmentId->validity_type !== 'not_applicable' && filled($governmentId->validity)) || filled($governmentId->processing_time))
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            @foreach(['purpose' => 'Purpose / Use', 'description' => 'Description', 'validity' => 'Validity Period', 'processing_time' => 'Processing Time'] as $field => $label)
                @if(filled($governmentId->$field) && ! ($field === 'validity' && $governmentId->validity_type === 'not_applicable'))
                    <section @class(['admin-panel p-6', 'md:col-span-2' => in_array($field, ['purpose', 'description'], true)])>
                        <h2 class="mb-3 text-base font-bold text-slate-900">{{ $label }}</h2>
                        <p class="whitespace-pre-line break-words text-sm leading-7 text-slate-600">{{ $governmentId->$field }}</p>
                    </section>
                @endif
            @endforeach
        </div>
    @endif

    {{-- REQUIREMENTS --}}
    @if(filled($governmentId->eligibility) || filled($governmentId->requirements) || filled($governmentId->prerequisite_notes))
        <section class="admin-panel mt-6 p-6">
            <h2 class="mb-6 text-lg font-semibold text-slate-900">Requirements & Eligibility</h2>
            <div class="space-y-6">
                @foreach(['eligibility' => 'Eligibility', 'requirements' => 'Requirements', 'prerequisite_notes' => 'Prerequisites / Dependencies'] as $field => $label)
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

    {{-- APPLICATION GUIDE --}}
    @if($governmentId->fees->isNotEmpty() || filled($governmentId->fee) || filled($governmentId->application_process) || filled($governmentId->renewal_process) || filled($governmentId->replacement_process))
    <section class="admin-panel mt-6 p-6">

        <h2 class="mb-6 text-lg font-semibold text-slate-900">
            Application Guide
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

            @foreach(['application_process' => 'Application Process / Steps', 'renewal_process' => 'Renewal Process', 'replacement_process' => 'Replacement Process'] as $field => $label)
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
    @if($governmentId->offices->isNotEmpty())
    <section class="admin-panel mt-6 p-6">
        <h2 class="text-lg font-semibold text-slate-900">Linked Offices</h2>
        <p class="mt-1 text-sm text-slate-500">Branches researched for this ID. Draft and inactive branches remain hidden from residents.</p>
        <div class="mt-5 space-y-4">
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

    {{-- SOURCES --}}
    @if(filled($governmentId->official_link) || filled($governmentId->official_sources))
        <section class="admin-panel mt-6 p-6">
            <h2 class="mb-6 text-lg font-semibold text-slate-900">Sources</h2>
            <div class="space-y-6">
                @if(filled($governmentId->official_link))
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-slate-700">Official Website / Application Link</h3>
                        <a href="{{ $governmentId->official_link }}" target="_blank" rel="noopener noreferrer" class="break-all text-sm font-medium text-[#012877] hover:underline">{{ $governmentId->official_link }}</a>
                    </div>
                @endif
                @if(filled($governmentId->official_sources))
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-slate-700">Official Sources</h3>
                        <p class="whitespace-pre-line break-words text-sm leading-7 text-slate-600">{{ $governmentId->official_sources }}</p>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <section class="admin-panel mt-6 p-6">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Edit History</h2>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-slate-500">{{ $lastEdit?->action === 'created' ? 'Created by' : 'Last edited by' }}</dt>
                <dd class="mt-1 font-medium text-slate-800">{{ $lastEdit?->user?->name ?? 'Editor not recorded' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-500">{{ $lastEdit?->action === 'created' ? 'Created on' : 'Last edited' }}</dt>
                <dd class="mt-1 text-sm text-slate-700">
                    @php($editTime = $lastEdit?->created_at ?? $governmentId->updated_at)
                    {{ $editTime ? $editTime->copy()->timezone('Asia/Manila')->format('F j, Y · g:i A').' PHT' : 'Not recorded' }}
                </dd>
            </div>
        </dl>
        @if(! $lastEdit)
            <p class="mt-3 text-xs text-slate-500">No recorded CMS edit history is available. The date above is the record’s last update time.</p>
        @endif
    </section>
</div>
@endsection
