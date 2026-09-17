@extends('admin.layouts.app')

@section('title', 'Audit Logs | IDireksyon')

@section('page_title', 'Audit Logs')

@section('content')

<div class="mx-auto max-w-7xl">

    {{-- HEADER --}}
    <div class="mb-7">

        <p class="admin-eyebrow mb-2">
            Administration
        </p>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            Audit Logs
        </h1>

        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
            Read-only history of changes made inside the IDireksyon CMS.
        </p>

    </div>


    {{-- SEARCH --}}
    <form
        method="GET"
        action="{{ route('admin.audit-logs.index') }}"
        class="admin-panel mb-5 flex flex-col gap-3 p-4 sm:flex-row sm:items-center"
    >

        <label class="min-w-0 flex-1">

            <span class="sr-only">
                Search audit logs
            </span>

            <input
                type="search"
                name="q"
                value="{{ is_string(request('q')) ? request('q') : '' }}"
                maxlength="255"
                placeholder="Search user or record..."
                class="admin-input"
            >

        </label>


        <div class="flex items-center gap-3">

            <button
                type="submit"
                class="admin-secondary"
            >
                Search
            </button>

            @if(request()->filled('q'))

                <a
                    href="{{ route('admin.audit-logs.index') }}"
                    class="text-sm font-medium text-[#012877] hover:underline"
                >
                    Clear
                </a>

            @endif

        </div>

    </form>


    {{-- TABLE --}}
    <div class="admin-panel overflow-x-auto">

        <table class="w-full text-left text-sm">

            <caption class="sr-only">
                CMS audit history
            </caption>


            <thead class="border-b border-slate-200 bg-slate-50">

                <tr class="text-slate-700">

                    <th class="whitespace-nowrap px-5 py-3 font-semibold">
                        Date & Time
                    </th>

                    <th class="px-5 py-3 font-semibold">
                        User
                    </th>

                    <th class="px-5 py-3 font-semibold">
                        Action
                    </th>

                    <th class="px-5 py-3 font-semibold">
                        Record
                    </th>

                    <th class="px-5 py-3 font-semibold">
                        Changed Fields
                    </th>

                </tr>

            </thead>


            <tbody class="divide-y divide-slate-100">

                @forelse($logs as $log)

                    <tr class="hover:bg-slate-50">

                        {{-- DATE --}}
                        <td class="whitespace-nowrap px-5 py-4 text-slate-500">

                            {{ $log->created_at->format('M j, Y') }}

                            <span class="block text-xs text-slate-400">
                                {{ $log->created_at->format('g:i A') }}
                            </span>

                        </td>


                        {{-- USER --}}
                        <td class="px-5 py-4">

                            <p class="font-medium text-slate-900">
                                {{ $log->user?->name ?? 'Former account' }}
                            </p>

                            @if($log->user?->username)

                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ '@'.$log->user->username }}
                                </p>

                            @endif

                        </td>


                        {{-- ACTION --}}
                        <td class="px-5 py-4">

                            @php
                                $action = strtolower($log->action);
                            @endphp

                            @if($action === 'created')

                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                    Created
                                </span>

                            @elseif($action === 'updated')

                                <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                    Updated
                                </span>

                            @elseif($action === 'deleted')

                                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">
                                    Deleted
                                </span>

                            @else

                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                    {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                </span>

                            @endif

                        </td>


                        {{-- RECORD --}}
                        <td class="px-5 py-4">

                            <p class="font-medium text-slate-800">
                                {{ $log->entity_name ?: 'Record #'.$log->entity_id }}
                            </p>

                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ ucfirst(str_replace('_', ' ', $log->entity_type)) }}
                            </p>

                        </td>


                        {{-- CHANGED FIELDS --}}
                        <td class="px-5 py-4 text-slate-500">

                            @if(!empty($log->changed_fields))

                                <div class="flex max-w-md flex-wrap gap-1.5">

                                    @foreach($log->changed_fields as $field)

                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600">
                                            {{ ucfirst(str_replace('_', ' ', $field)) }}
                                        </span>

                                    @endforeach

                                </div>

                            @else

                                <span class="text-slate-400">
                                    —
                                </span>

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="px-6 py-14 text-center"
                        >

                            <p class="font-medium text-slate-800">
                                No audit logs yet.
                            </p>

                            <p class="mt-1 text-sm text-slate-500">
                                CMS changes will appear here automatically.
                            </p>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    {{-- PAGINATION --}}
    @if($logs->hasPages())

        <div class="mt-6">
            {{ $logs->links() }}
        </div>

    @endif

</div>

@endsection