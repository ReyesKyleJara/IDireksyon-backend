@extends('admin.layouts.app')

@section('title', 'IDs & Credentials | IDireksyon')

@section('page_title', 'IDs & Credentials')

@section('content')

    <div class="mx-auto max-w-7xl">

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

                <input type="hidden" name="level" value="{{ request('level') }}">
                <input type="hidden" name="category" value="{{ request('category') }}">
                <input type="hidden" name="sort" value="{{ request('sort', 'name_asc') }}">

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
            <form method="GET" action="{{ url()->current() }}">
                <input type="hidden" name="q" value="{{ request('q') }}">
                <input type="hidden" name="level" value="{{ request('level') }}">
                <input type="hidden" name="category" value="{{ request('category') }}">

                <select
                    name="sort"
                    onchange="this.form.submit()"
                    class="admin-input min-w-[170px]"
                >
                    <option value="name_asc" @selected(($sort ?? 'name_asc') === 'name_asc')>
                        Name A–Z
                    </option>

                    <option value="name_desc" @selected(($sort ?? '') === 'name_desc')>
                        Name Z–A
                    </option>

                    <option value="level" @selected(($sort ?? '') === 'level')>
                        Level
                    </option>

                    <option value="category" @selected(($sort ?? '') === 'category')>
                        Category
                    </option>

                    <option value="recent" @selected(($sort ?? '') === 'recent')>
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
                                    'q' => request('q'),
                                    'category' => request('category'),
                                    'sort' => request('sort', 'name_asc')
                                ])) }}"
                                class="rounded-lg border px-3 py-2 text-xs font-medium
                                {{ !request('level')
                                    ? 'border-[#012877] bg-[#012877] text-white'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}"
                            >
                                All
                            </a>

                            @foreach(['Barangay', 'Municipal / LGU', 'National'] as $level)

                                <a
                                    href="{{ route('admin.government-ids.index', array_filter([
                                        'q' => request('q'),
                                        'level' => $level,
                                        'category' => request('category'),
                                        'sort' => request('sort', 'name_asc')
                                    ])) }}"
                                    class="rounded-lg border px-3 py-2 text-xs font-medium
                                    {{ request('level') === $level
                                        ? 'border-[#012877] bg-[#012877] text-white'
                                        : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}"
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
                                    'q' => request('q'),
                                    'level' => request('level'),
                                    'sort' => request('sort', 'name_asc')
                                ])) }}"
                                class="rounded-lg border px-3 py-2 text-xs font-medium
                                {{ !request('category')
                                    ? 'border-[#012877] bg-[#012877] text-white'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}"
                            >
                                All
                            </a>

                            @foreach([
                                'Identity ID',
                                'Sector-Specific ID',
                                'Driving Credential',
                                'Professional Credential',
                                'Tax ID',
                                'Travel Document'
                            ] as $category)

                                <a
                                    href="{{ route('admin.government-ids.index', array_filter([
                                        'q' => request('q'),
                                        'level' => request('level'),
                                        'category' => $category
                                    ])) }}"
                                    class="rounded-lg border px-3 py-2 text-xs font-medium
                                    {{ request('category') === $category
                                        ? 'border-[#012877] bg-[#012877] text-white'
                                        : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}"
                                >
                                    {{ $category }}
                                </a>

                            @endforeach

                        </div>

                    </div>


                    {{-- CLEAR --}}
                    @if(request('q') || request('level') || request('category'))

                        <div class="mt-5 border-t border-slate-100 pt-4">

                            <a
                                href="{{ route('admin.government-ids.index') }}"
                                class="text-xs font-medium text-red-600 hover:underline"
                            >
                                Clear all filters
                            </a>

                        </div>

                    @endif

                </div>

            </div>

        </div>



        <div class="admin-panel overflow-hidden">

            <div class="overflow-x-auto">

                <table class="min-w-full divide-y divide-slate-200">

                    <thead class="bg-slate-50">

                        <tr>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Name
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Level
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Category
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Issued By
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Actions
                            </th>

                        </tr>

                    </thead>



                    <tbody class="divide-y divide-slate-100 bg-white">

                        @forelse($governmentIds as $governmentId)

                            <tr>

                                {{-- NAME --}}
                                <td class="px-5 py-4">

                                    <p class="font-semibold text-slate-900">
                                        {{ $governmentId->name }}
                                    </p>

                                </td>


                                {{-- LEVEL --}}
                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ $governmentId->level ?: '—' }}
                                </td>


                                {{-- CATEGORY --}}
                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ $governmentId->category ?: '—' }}
                                </td>


                                {{-- ISSUED BY --}}
                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ $governmentId->issued_by ?: '—' }}
                                </td>


                                {{-- ACTIONS --}}
                                <td class="px-5 py-4">

                                    <div class="flex justify-end gap-2">

                                        <a
                                            href="{{ route('admin.government-ids.edit', $governmentId) }}"
                                            class="admin-secondary"
                                        >
                                            Edit
                                        </a>


                                        <form
                                            method="POST"
                                            action="{{ route('admin.government-ids.destroy', $governmentId) }}"
                                            onsubmit="return confirm('Delete this ID or credential?')"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>


                        @empty

                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center">

                                    @if(request('q') || request('level') || request('category'))

                                        <p class="font-semibold text-slate-800">
                                            No matching IDs or credentials found.
                                        </p>

                                        <p class="mt-1 text-sm text-slate-500">
                                            Try another search or clear your active filters.
                                        </p>

                                        <a
                                            href="{{ route('admin.government-ids.index') }}"
                                            class="admin-secondary mt-5"
                                        >
                                            Clear Search & Filters
                                        </a>

                                    @else

                                        <p class="font-semibold text-slate-800">
                                            No IDs or Credentials yet.
                                        </p>

                                        <p class="mt-1 text-sm text-slate-500">
                                            Add your first ID or credential to get started.
                                        </p>

                                        <a
                                            href="{{ route('admin.government-ids.create') }}"
                                            class="admin-primary mt-5"
                                        >
                                            Add ID or Credential
                                        </a>

                                    @endif

                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

@endsection