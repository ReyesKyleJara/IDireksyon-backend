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

        <div
            class="flex h-44 items-center justify-center
                   bg-slate-100 text-slate-400"
        >

            <div class="text-center">

                <svg
                    aria-hidden="true"
                    class="mx-auto mb-2 h-12 w-12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <rect
                        x="2"
                        y="4"
                        width="20"
                        height="16"
                        rx="3"
                    />

                    <circle
                        cx="8"
                        cy="10"
                        r="2"
                    />

                    <path
                        d="M5 16c0-3 6-3 6 0M14 9h5M14 13h5"
                    />
                </svg>

                <span class="text-xs">
                    No image yet
                </span>

            </div>

        </div>


        <div class="p-6 sm:p-8">

            <p class="admin-eyebrow mb-3">
                {{ $governmentId->category ?: 'No category' }}

                |

                {{ $governmentId->level ?: 'No level' }}
            </p>


            <h1
                class="break-words text-3xl font-bold
                       tracking-tight text-slate-900"
            >
                {{ $governmentId->name }}
            </h1>


            <p class="mt-3 break-words text-sm text-slate-500">

                Issued by:

                @if($governmentId->agency)

                    <span class="font-medium text-slate-700">
                        {{ $governmentId->agency->name }}

                        @if($governmentId->agency->acronym)
                            ({{ $governmentId->agency->acronym }})
                        @endif
                    </span>

                @else

                    Not available yet.

                @endif

            </p>


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
    <div class="mt-6 grid gap-5 md:grid-cols-2">

        {{-- PURPOSE --}}
        <section class="admin-panel p-6 md:col-span-2">

            <h2 class="mb-3 text-base font-bold text-slate-900">
                Purpose / Use
            </h2>

            @if(filled($governmentId->purpose))

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{ $governmentId->purpose }}
                </p>

            @else

                <p class="text-sm text-slate-400">
                    Not available yet.
                </p>

            @endif

        </section>


        {{-- DESCRIPTION --}}
        <section class="admin-panel p-6 md:col-span-2">

            <h2 class="mb-3 text-base font-bold text-slate-900">
                Description
            </h2>

            @if(filled($governmentId->description))

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{ $governmentId->description }}
                </p>

            @else

                <p class="text-sm text-slate-400">
                    Not available yet.
                </p>

            @endif

        </section>


        {{-- VALIDITY --}}
        @if($governmentId->validity_type !== 'not_applicable')
            <section class="admin-panel p-6">

                <h2 class="mb-3 text-base font-bold text-slate-900">
                    Validity Period
                </h2>

                @if(filled($governmentId->validity))

                    <p class="text-sm font-medium text-slate-700">
                        {{ $governmentId->validity }}
                    </p>

                @else

                    <p class="text-sm text-slate-400">
                        Not available yet.
                    </p>

                @endif

            </section>
        @endif


        {{-- PROCESSING TIME --}}
        <section class="admin-panel p-6">

            <h2 class="mb-3 text-base font-bold text-slate-900">
                Processing Time
            </h2>

            @if(filled($governmentId->processing_time))

                <p class="text-sm font-medium text-slate-700">
                    {{ $governmentId->processing_time }}
                </p>

            @else

                <p class="text-sm text-slate-400">
                    Not available yet.
                </p>

            @endif

        </section>

    </div>


    {{-- REQUIREMENTS --}}
    <section class="admin-panel mt-6 p-6">

        <div class="mb-6">

            <h2 class="text-lg font-semibold text-slate-900">
                Requirements & Eligibility
            </h2>

        </div>


        <div class="space-y-6">

            {{-- ELIGIBILITY --}}
            <div>

                <h3 class="mb-2 text-sm font-semibold text-slate-700">
                    Eligibility
                </h3>

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{ $governmentId->eligibility ?: 'Not available yet.' }}
                </p>

            </div>


            {{-- REQUIREMENTS --}}
            <div class="border-t border-slate-100 pt-5">

                <h3 class="mb-2 text-sm font-semibold text-slate-700">
                    Requirements
                </h3>

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{ $governmentId->requirements ?: 'Not available yet.' }}
                </p>

            </div>


            {{-- PREREQUISITES --}}
            <div class="border-t border-slate-100 pt-5">

                <h3 class="mb-2 text-sm font-semibold text-slate-700">
                    Prerequisites / Dependencies
                </h3>

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{
                        $governmentId->prerequisite_notes
                        ?: 'Not available yet.'
                    }}
                </p>

            </div>

        </div>

    </section>


    {{-- APPLICATION GUIDE --}}
    <section class="admin-panel mt-6 p-6">

        <h2 class="mb-6 text-lg font-semibold text-slate-900">
            Application Guide
        </h2>


        <div class="space-y-7">

            {{-- MULTIPLE FEES --}}
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


                                        @default

                                            Not available

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


                @else

                    <p class="text-sm text-slate-400">
                        Not available yet.
                    </p>

                @endif

            </div>


            {{-- APPLICATION PROCESS --}}
            <div class="border-t border-slate-100 pt-6">

                <h3 class="mb-2 text-sm font-semibold text-slate-700">
                    Application Process / Steps
                </h3>

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{
                        $governmentId->application_process
                        ?: 'Not available yet.'
                    }}
                </p>

            </div>


            {{-- RENEWAL PROCESS --}}
            <div class="border-t border-slate-100 pt-6">

                <h3 class="mb-2 text-sm font-semibold text-slate-700">
                    Renewal Process
                </h3>

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{
                        $governmentId->renewal_process
                        ?: 'Not available yet.'
                    }}
                </p>

            </div>


            {{-- REPLACEMENT PROCESS --}}
            <div class="border-t border-slate-100 pt-6">

                <h3 class="mb-2 text-sm font-semibold text-slate-700">
                    Replacement Process
                </h3>

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{
                        $governmentId->replacement_process
                        ?: 'Not available yet.'
                    }}
                </p>

            </div>

        </div>

    </section>


    {{-- OFFICE --}}
    <section class="admin-panel mt-6 p-6">

        <h2 class="mb-6 text-lg font-semibold text-slate-900">
            Office Information
        </h2>


        <div class="grid gap-6 md:grid-cols-2">

            {{-- LOCATION --}}
            <div>

                <h3 class="mb-2 text-sm font-semibold text-slate-700">
                    Government Office / Location
                </h3>

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{
                        $governmentId->office_location
                        ?: 'Not available yet.'
                    }}
                </p>

            </div>


            {{-- OFFICE HOURS --}}
            <div>

                <h3 class="mb-2 text-sm font-semibold text-slate-700">
                    Office Hours
                </h3>

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{
                        $governmentId->office_hours
                        ?: 'Not available yet.'
                    }}
                </p>

            </div>

        </div>

    </section>


    {{-- SOURCES --}}
    <section class="admin-panel mt-6 p-6">

        <h2 class="mb-6 text-lg font-semibold text-slate-900">
            Sources & Verification
        </h2>


        <div class="space-y-6">

            {{-- OFFICIAL LINK --}}
            <div>

                <h3 class="mb-2 text-sm font-semibold text-slate-700">
                    Official Website / Application Link
                </h3>


                @if($governmentId->official_link)

                    <a
                        href="{{ $governmentId->official_link }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="break-all text-sm font-medium
                               text-[#012877] hover:underline"
                    >
                        {{ $governmentId->official_link }}
                    </a>

                @else

                    <p class="text-sm text-slate-400">
                        Not available yet.
                    </p>

                @endif

            </div>


            {{-- OFFICIAL SOURCES --}}
            <div class="border-t border-slate-100 pt-5">

                <h3 class="mb-2 text-sm font-semibold text-slate-700">
                    Official Sources
                </h3>

                <p
                    class="whitespace-pre-line break-words
                           text-sm leading-7 text-slate-600"
                >
                    {{
                        $governmentId->official_sources
                        ?: 'Not available yet.'
                    }}
                </p>

            </div>


            {{-- VERIFICATION STATUS --}}
            <div class="border-t border-slate-100 pt-5">

                <h3 class="mb-3 text-sm font-semibold text-slate-700">
                    Verification Status
                </h3>


                @if($governmentId->last_verified_at)

                    <div class="space-y-2">

                        <div
                            class="inline-flex items-center gap-2 rounded-full
                                   bg-emerald-50 px-3 py-1
                                   text-xs font-medium text-emerald-700"
                        >
                            <span
                                class="h-2 w-2 rounded-full
                                       bg-emerald-500"
                            ></span>

                            Verified
                        </div>


                        <div class="space-y-1 text-sm text-slate-600">

                            <p>

                                <span class="font-medium text-slate-700">
                                    Last verified:
                                </span>

                                {{
                                    $governmentId
                                        ->last_verified_at
                                        ->format('F j, Y')
                                }}

                            </p>


                            <p>

                                <span class="font-medium text-slate-700">
                                    Verified by:
                                </span>

                                {{
                                    $governmentId->lastVerifier?->name
                                    ?? $governmentId->lastVerifier?->email
                                    ?? 'Unknown user'
                                }}

                            </p>

                        </div>

                    </div>

                @else

                    <div
                        class="inline-flex items-center gap-2 rounded-full
                               bg-amber-50 px-3 py-1
                               text-xs font-medium text-amber-700"
                    >
                        <span
                            class="h-2 w-2 rounded-full bg-amber-500"
                        ></span>

                        Not yet verified
                    </div>

                @endif

            </div>

        </div>

    </section>

</div>

@endsection