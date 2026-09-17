@extends('admin.layouts.app')

@section('title', 'Admin Users | IDireksyon')

@section('page_title', 'Admin Users')

@section('content')

<div class="mx-auto max-w-7xl">

    {{-- HEADER --}}
    <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">

        <div>
            <p class="admin-eyebrow mb-2">
                User Management
            </p>

            <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                Admin Users
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Manage Super Admin and Researcher accounts with access to the CMS.
            </p>
        </div>

        <a
            href="{{ route('admin.accounts.create') }}"
            class="admin-primary shrink-0"
        >
            <svg
                aria-hidden="true"
                class="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path d="M12 5v14M5 12h14"></path>
            </svg>

            Add Admin
        </a>

    </div>


    {{-- TABLE --}}
    <div class="admin-panel overflow-x-auto">

        <table class="w-full text-left text-sm">

            <thead class="border-b border-slate-200 bg-slate-50">

                <tr class="text-slate-700">

                    <th class="px-5 py-3 font-semibold">
                        Name
                    </th>

                    <th class="px-5 py-3 font-semibold">
                        Username
                    </th>

                    <th class="px-5 py-3 font-semibold">
                        Email
                    </th>

                    <th class="px-5 py-3 font-semibold">
                        Role
                    </th>

                    <th class="px-5 py-3 font-semibold">
                        Status
                    </th>

                    <th class="px-5 py-3 text-right font-semibold">
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody class="divide-y divide-slate-200">

                @forelse($accounts as $account)

                    <tr class="hover:bg-slate-50">

                        {{-- NAME --}}
                        <td class="px-5 py-4">

                            <div class="flex items-center gap-3">

                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E8EEF9] text-sm font-bold text-[#012877]"
                                >
                                    {{ strtoupper(mb_substr($account->name, 0, 1)) }}
                                </div>

                                <div>

                                    <p class="font-medium text-slate-900">
                                        {{ $account->name }}
                                    </p>

                                    @if(auth()->id() === $account->id)
                                        <p class="mt-0.5 text-xs text-slate-400">
                                            You
                                        </p>
                                    @endif

                                </div>

                            </div>

                        </td>


                        {{-- USERNAME --}}
                        <td class="px-5 py-4 text-slate-600">
                            {{ $account->username }}
                        </td>


                        {{-- EMAIL --}}
                        <td class="px-5 py-4 text-slate-600">
                            {{ $account->email }}
                        </td>


                        {{-- ROLE --}}
                        <td class="px-5 py-4">

                            <span
                                class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700"
                            >
                                {{ $roles[$account->role] ?? $account->role }}
                            </span>

                        </td>


                        {{-- STATUS --}}
                        <td class="px-5 py-4">

                            @if($account->is_active)

                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>

                                    Active
                                </span>

                            @else

                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>

                                    Inactive
                                </span>

                            @endif

                        </td>


                        {{-- ACTIONS --}}
                        <td class="px-5 py-4 text-right">

                            <a
                                href="{{ route('admin.accounts.edit', $account) }}"
                                class="font-medium text-[#012877] hover:underline"
                            >
                                Edit
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            class="px-6 py-14 text-center"
                        >
                            <p class="font-medium text-slate-800">
                                No admin accounts found.
                            </p>

                            <p class="mt-1 text-sm text-slate-500">
                                Add an account to give someone access to the CMS.
                            </p>
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    {{-- PAGINATION --}}
    @if($accounts->hasPages())

        <div class="mt-6">
            {{ $accounts->links() }}
        </div>

    @endif

</div>

@endsection