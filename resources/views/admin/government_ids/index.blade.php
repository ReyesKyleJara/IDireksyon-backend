@extends('admin.layouts.app')

@section('title', 'IDs & Credentials | IDireksyon')
@section('page_title', 'IDs & Credentials')

@section('content')

@php
    $viewMode = request('view') === 'table' ? 'table' : 'grid';

    $directoryQuery = request()->only([
        'q',
        'level',
        'category',
        'sort',
    ]);

    $directoryQuery['view'] = $viewMode;
@endphp


<div class="mx-auto max-w-7xl">

    {{-- PAGE HEADER --}}
    <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">

        <div>

            <p class="admin-eyebrow mb-2">
                Content Directory
            </p>

            <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                IDs & Credentials
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Add and manage government-issued IDs and credentials used by IDireksyon.
            </p>

        </div>


        <a
            href="{{ route('admin.government-ids.create') }}"
            class="admin-primary"
        >
            Add ID or Credential
        </a>

    </div>


    {{-- SEARCH + FILTERS --}}
    <div
        class="mb-5 flex flex-col gap-3 sm:flex-row"
        x-data="{ filtersOpen: false }"
    >

        {{-- SEARCH --}}
        <form
            method="GET"
            action="{{ route('admin.government-ids.index') }}"
            class="min-w-0 flex-1"
        >

            <input
                type="hidden"
                name="view"
                value="{{ $viewMode }}"
            >

            <input
                type="hidden"
                name="level"
                value="{{ request('level') }}"
            >

            <input
                type="hidden"
                name="category"
                value="{{ request('category') }}"
            >

            <input
                type="hidden"
                name="sort"
                value="{{ request('sort', 'name_asc') }}"
            >


            <div class="flex gap-2">

                <input
                    type="search"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Search IDs or issuing agency..."
                    class="admin-input"
                >

                <button
                    type="submit"
                    class="admin-secondary"
                >
                    Search
                </button>

            </div>

        </form>


        {{-- SORT --}}
        <form
            method="GET"
            action="{{ route('admin.government-ids.index') }}"
        >

            <input
                type="hidden"
                name="view"
                value="{{ $viewMode }}"
            >

            <input
                type="hidden"
                name="q"
                value="{{ request('q') }}"
            >

            <input
                type="hidden"
                name="level"
                value="{{ request('level') }}"
            >

            <input
                type="hidden"
                name="category"
                value="{{ request('category') }}"
            >


            <select
                name="sort"
                onchange="this.form.submit()"
                class="admin-input min-w-[170px]"
            >

                <option
                    value="name_asc"
                    @selected(($sort ?? 'name_asc') === 'name_asc')
                >
                    Name A–Z
                </option>

                <option
                    value="name_desc"
                    @selected(($sort ?? '') === 'name_desc')
                >
                    Name Z–A
                </option>

                <option
                    value="level"
                    @selected(($sort ?? '') === 'level')
                >
                    Level
                </option>

                <option
                    value="category"
                    @selected(($sort ?? '') === 'category')
                >
                    Category
                </option>

                <option
                    value="recent"
                    @selected(($sort ?? '') === 'recent')
                >
                    Recently Added
                </option>

            </select>

        </form>


        {{-- FILTER BUTTON --}}
        <div class="relative">

            <button
                type="button"
                @click="filtersOpen = !filtersOpen"
                class="admin-secondary flex h-full items-center gap-2"
            >

                <svg
                    class="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M4 6h16"></path>
                    <path d="M7 12h10"></path>
                    <path d="M10 18h4"></path>
                </svg>

                Filters

                @if(request('level') || request('category'))
                    <span class="h-2 w-2 rounded-full bg-[#012877]"></span>
                @endif

            </button>


            {{-- FILTER DROPDOWN --}}
            <div
                x-cloak
                x-show="filtersOpen"
                @click.outside="filtersOpen = false"
                x-transition
                class="absolute right-0 z-40 mt-2 w-80 rounded-xl border border-slate-200 bg-white p-5 shadow-xl"
            >

                {{-- LEVEL --}}
                <div>

                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Level
                    </p>

                    <div class="flex flex-wrap gap-2">

                        <a
                            href="{{ route('admin.government-ids.index', array_filter([
                                'view' => $viewMode,
                                'q' => request('q'),
                                'category' => request('category'),
                                'sort' => request('sort', 'name_asc'),
                            ])) }}"
                            class="rounded-lg border px-3 py-2 text-xs font-medium
                            {{
                                !request('level')
                                    ? 'border-[#012877] bg-[#012877] text-white'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50'
                            }}"
                        >
                            All
                        </a>


                        @foreach([
                            'Barangay',
                            'Municipal / LGU',
                            'National',
                        ] as $level)

                            <a
                                href="{{ route('admin.government-ids.index', array_filter([
                                    'view' => $viewMode,
                                    'q' => request('q'),
                                    'level' => $level,
                                    'category' => request('category'),
                                    'sort' => request('sort', 'name_asc'),
                                ])) }}"
                                class="rounded-lg border px-3 py-2 text-xs font-medium
                                {{
                                    request('level') === $level
                                        ? 'border-[#012877] bg-[#012877] text-white'
                                        : 'border-slate-200 text-slate-600 hover:bg-slate-50'
                                }}"
                            >
                                {{ $level }}
                            </a>

                        @endforeach

                    </div>

                </div>


                {{-- CATEGORY --}}
                <div class="mt-5">

                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Category
                    </p>

                    <div class="flex flex-wrap gap-2">

                        <a
                            href="{{ route('admin.government-ids.index', array_filter([
                                'view' => $viewMode,
                                'q' => request('q'),
                                'level' => request('level'),
                                'sort' => request('sort', 'name_asc'),
                            ])) }}"
                            class="rounded-lg border px-3 py-2 text-xs font-medium
                            {{
                                !request('category')
                                    ? 'border-[#012877] bg-[#012877] text-white'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50'
                            }}"
                        >
                            All
                        </a>


                        @foreach([
                            'Identity ID',
                            'Sector-Specific ID',
                            'Driving Credential',
                            'Professional Credential',
                            'Tax ID',
                            'Travel Document',
                        ] as $category)

                            <a
                                href="{{ route('admin.government-ids.index', array_filter([
                                    'view' => $viewMode,
                                    'q' => request('q'),
                                    'level' => request('level'),
                                    'category' => $category,
                                    'sort' => request('sort', 'name_asc'),
                                ])) }}"
                                class="rounded-lg border px-3 py-2 text-xs font-medium
                                {{
                                    request('category') === $category
                                        ? 'border-[#012877] bg-[#012877] text-white'
                                        : 'border-slate-200 text-slate-600 hover:bg-slate-50'
                                }}"
                            >
                                {{ $category }}
                            </a>

                        @endforeach

                    </div>

                </div>


                {{-- CLEAR FILTERS --}}
                @if(
                    request('q')
                    || request('level')
                    || request('category')
                )

                    <div class="mt-5 border-t border-slate-100 pt-4">

                        <a
                            href="{{ route('admin.government-ids.index', [
                                'view' => $viewMode,
                            ]) }}"
                            class="text-xs font-medium text-red-600 hover:underline"
                        >
                            Clear all filters
                        </a>

                    </div>

                @endif

            </div>

        </div>

    </div>


    {{-- DIRECTORY TOOLBAR --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">

        <p class="text-sm text-slate-500">
            {{ $governmentIds->count() }}
            {{ $governmentIds->count() === 1 ? 'record' : 'records' }}
        </p>


        <nav
            aria-label="Directory view"
            class="flex gap-2"
        >

            @foreach([
                'grid' => 'Grid',
                'table' => 'Table',
            ] as $mode => $label)

                <a
                    href="{{ route(
                        'admin.government-ids.index',
                        array_merge(
                            $directoryQuery,
                            ['view' => $mode]
                        )
                    ) }}"
                    @if($viewMode === $mode)
                        aria-current="page"
                    @endif
                    class="{{
                        $viewMode === $mode
                            ? 'admin-primary'
                            : 'admin-secondary'
                    }}"
                >
                    {{ $label }}
                </a>

            @endforeach

        </nav>

    </div>


    {{-- GRID VIEW --}}
    @if($viewMode === 'grid')

        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">

            @forelse($governmentIds as $governmentId)

                <a
                    href="{{ route(
                        'admin.government-ids.show',
                        array_merge(
                            [
                                'government_id' => $governmentId->id,
                            ],
                            $directoryQuery
                        )
                    ) }}"
                    class="admin-panel block overflow-hidden transition
                           hover:shadow-lg
                           focus-visible:outline
                           focus-visible:outline-2
                           focus-visible:outline-offset-2
                           focus-visible:outline-[#012877]"
                >

                    {{-- PLACEHOLDER IMAGE --}}
                    <div class="flex h-44 items-center justify-center bg-slate-100 text-slate-400">

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


                    <div class="p-5">

                        <h2 class="break-words text-lg font-bold text-slate-900">
                            {{ $governmentId->name }}
                        </h2>


                        <p class="mt-2 break-words text-sm text-slate-500">

                            @if($governmentId->agency)

                                {{ $governmentId->agency->name }}

                                @if($governmentId->agency->acronym)
                                    ({{ $governmentId->agency->acronym }})
                                @endif

                            @else

                                Issuing agency not provided

                            @endif

                        </p>


                        <div class="mt-4 flex flex-wrap items-center gap-2">

                            <span class="text-xs font-medium text-[#012877]">
                                {{ $governmentId->category ?: 'No category' }}
                            </span>

                            <span class="text-xs text-slate-300">
                                •
                            </span>

                            <span class="text-xs font-medium text-[#012877]">
                                {{ $governmentId->level ?: 'No level' }}
                            </span>

                        </div>


                        {{-- VERIFICATION STATUS --}}
                        <div class="mt-4">

                            @if($governmentId->last_verified_at)

                                <span
                                    class="inline-flex items-center rounded-full
                                           bg-emerald-50 px-2.5 py-1
                                           text-xs font-medium text-emerald-700"
                                >
                                    Verified
                                    {{ $governmentId->last_verified_at->format('M j, Y') }}
                                </span>

                            @else

                                <span
                                    class="inline-flex items-center rounded-full
                                           bg-amber-50 px-2.5 py-1
                                           text-xs font-medium text-amber-700"
                                >
                                    Not yet verified
                                </span>

                            @endif

                        </div>

                    </div>

                </a>

            @empty

                <div class="admin-panel p-8 text-center sm:col-span-2 xl:col-span-3">

                    <p class="font-semibold text-slate-900">

                        {{
                            request()->filled('q')
                            || request()->filled('level')
                            || request()->filled('category')
                                ? 'No matching IDs or credentials found.'
                                : 'No IDs or credentials yet.'
                        }}

                    </p>


                    @if(
                        request()->filled('q')
                        || request()->filled('level')
                        || request()->filled('category')
                    )

                        <a
                            href="{{ route('admin.government-ids.index', [
                                'view' => $viewMode,
                            ]) }}"
                            class="admin-secondary mt-4"
                        >
                            Reset search and filters
                        </a>

                    @endif

                </div>

            @endforelse

        </div>


    {{-- TABLE VIEW --}}
    @else

        <div class="admin-panel overflow-hidden">

            <div class="overflow-x-auto">

                <table class="min-w-full divide-y divide-slate-200">

                    <thead class="bg-slate-50">

                        <tr>

                            <th
                                scope="col"
                                class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500"
                            >
                                Name
                            </th>

                            <th
                                scope="col"
                                class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500"
                            >
                                Issuing Agency
                            </th>

                            <th
                                scope="col"
                                class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500"
                            >
                                Level
                            </th>

                            <th
                                scope="col"
                                class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500"
                            >
                                Category
                            </th>

                            <th
                                scope="col"
                                class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500"
                            >
                                Verification
                            </th>

                            <th
                                scope="col"
                                class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-slate-500"
                            >
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-slate-100 bg-white">

                        @forelse($governmentIds as $governmentId)

                            <tr class="hover:bg-slate-50">

                                {{-- NAME --}}
                                <td class="px-6 py-4">

                                    <a
                                        href="{{ route(
                                            'admin.government-ids.show',
                                            array_merge(
                                                [
                                                    'government_id' => $governmentId->id,
                                                ],
                                                $directoryQuery
                                            )
                                        ) }}"
                                        class="font-semibold text-slate-900 hover:text-[#012877]"
                                    >
                                        {{ $governmentId->name }}
                                    </a>

                                </td>


                                {{-- AGENCY --}}
                                <td class="px-6 py-4 text-sm text-slate-600">

                                    @if($governmentId->agency)

                                        {{ $governmentId->agency->name }}

                                        @if($governmentId->agency->acronym)

                                            <span class="text-slate-400">
                                                ({{ $governmentId->agency->acronym }})
                                            </span>

                                        @endif

                                    @else

                                        <span class="text-slate-400">
                                            Not provided
                                        </span>

                                    @endif

                                </td>


                                {{-- LEVEL --}}
                                <td class="px-6 py-4 text-sm text-slate-600">

                                    {{ $governmentId->level ?: '—' }}

                                </td>


                                {{-- CATEGORY --}}
                                <td class="px-6 py-4 text-sm text-slate-600">

                                    {{ $governmentId->category ?: '—' }}

                                </td>


                                {{-- VERIFICATION --}}
                                <td class="px-6 py-4">

                                    @if($governmentId->last_verified_at)

                                        <div>

                                            <span
                                                class="inline-flex items-center rounded-full
                                                       bg-emerald-50 px-2.5 py-1
                                                       text-xs font-medium text-emerald-700"
                                            >
                                                Verified
                                            </span>

                                            <p class="mt-1 text-xs text-slate-400">
                                                {{ $governmentId->last_verified_at->format('M j, Y') }}
                                            </p>

                                        </div>

                                    @else

                                        <span
                                            class="inline-flex items-center rounded-full
                                                   bg-amber-50 px-2.5 py-1
                                                   text-xs font-medium text-amber-700"
                                        >
                                            Not verified
                                        </span>

                                    @endif

                                </td>


                                {{-- ACTIONS --}}
                                <td class="px-6 py-4 text-right">

                                    <div class="flex items-center justify-end gap-2">

                                        <a
                                            href="{{ route(
                                                'admin.government-ids.show',
                                                array_merge(
                                                    [
                                                        'government_id' => $governmentId->id,
                                                    ],
                                                    $directoryQuery
                                                )
                                            ) }}"
                                            class="admin-secondary"
                                        >
                                            View
                                        </a>


                                        <a
                                            href="{{ route(
                                                'admin.government-ids.edit',
                                                $governmentId
                                            ) }}"
                                            class="admin-secondary"
                                        >
                                            Edit
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="6"
                                    class="px-6 py-10 text-center"
                                >

                                    <p class="font-semibold text-slate-900">

                                        {{
                                            request()->filled('q')
                                            || request()->filled('level')
                                            || request()->filled('category')
                                                ? 'No matching IDs or credentials found.'
                                                : 'No IDs or credentials yet.'
                                        }}

                                    </p>


                                    @if(
                                        request()->filled('q')
                                        || request()->filled('level')
                                        || request()->filled('category')
                                    )

                                        <a
                                            href="{{ route(
                                                'admin.government-ids.index',
                                                ['view' => $viewMode]
                                            ) }}"
                                            class="admin-secondary mt-4"
                                        >
                                            Reset search and filters
                                        </a>

                                    @endif

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    @endif

</div>

@endsection