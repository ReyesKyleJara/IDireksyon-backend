<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'IDireksyon Admin Panel')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>


<body class="bg-slate-50 text-slate-900">

    <div
        class="min-h-screen flex flex-col md:flex-row"
        x-data="{ navigationOpen: false }"
    >

        {{-- ========================================================= --}}
        {{-- MOBILE HEADER --}}
        {{-- ========================================================= --}}

        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 md:hidden">

            <span class="text-sm font-semibold text-[#012877]">
                IDireksyon Admin Panel
            </span>

            <button
                type="button"
                class="rounded-md border border-slate-200 px-3 py-2 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#012877]"
                @click="navigationOpen = !navigationOpen"
                :aria-expanded="navigationOpen.toString()"
                aria-expanded="false"
                aria-controls="admin-sidebar"
            >
                Menu
            </button>

        </div>



        {{-- ========================================================= --}}
        {{-- SIDEBAR --}}
        {{-- ========================================================= --}}

        <aside
            id="admin-sidebar"
            class="hidden w-full bg-white border-b border-slate-200 flex-col shrink-0 md:sticky md:top-0 md:flex md:h-screen md:w-64 md:border-b-0 md:border-r"
            :class="{ 'hidden': !navigationOpen, 'flex': navigationOpen }"
            @keydown.escape.window="navigationOpen = false"
        >


            {{-- BRAND --}}

            <div class="px-6 py-6">

                <div class="flex items-center gap-3">

                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center"
                        style="background-color: #012877;"
                    >
                        <span class="text-white font-bold text-sm">
                            ID
                        </span>
                    </div>

                    <div>

                        <h1 class="text-base font-bold tracking-tight text-slate-900">
                            IDireksyon
                        </h1>

                        <p class="text-xs text-slate-400 mt-0.5">
                            Admin Panel
                        </p>

                    </div>

                </div>

            </div>



            {{-- ========================================================= --}}
            {{-- NAVIGATION --}}
            {{-- ========================================================= --}}

            <nav
                aria-label="Admin navigation"
                class="flex-1 overflow-y-auto px-3 pb-6"
            >


                {{-- OVERVIEW --}}

                    <div class="mb-7">

                        <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                            Overview
                        </p>


                        {{-- Dashboard --}}
                        <a
                            href="{{ route('admin.dashboard') }}"
                            @if(request()->routeIs('admin.dashboard'))
                                aria-current="page"
                            @endif
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition
                            {{ request()->routeIs('admin.dashboard')
                                ? 'bg-[#012877] text-white shadow-sm'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-[#012877]' }}"
                        >

                            <svg
                                aria-hidden="true"
                                class="w-[18px] h-[18px] shrink-0"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                viewBox="0 0 24 24"
                            >
                                <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                                <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                                <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                                <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                            </svg>

                            Dashboard

                        </a>

                    </div>




                {{-- CONTENT --}}

<div class="mb-7">

    <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
        Content
    </p>


    {{-- IDs & Credentials --}}
    <a
        href="{{ route('admin.government-ids.index') }}"
        @if(request()->routeIs('admin.government-ids.*'))
            aria-current="page"
        @endif
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition
        {{ request()->routeIs('admin.government-ids.*')
            ? 'bg-[#012877] text-white shadow-sm'
            : 'text-slate-600 hover:bg-slate-50 hover:text-[#012877]' }}"
    >

        <svg
            aria-hidden="true"
            class="w-[18px] h-[18px] shrink-0"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            viewBox="0 0 24 24"
        >
            <rect x="3" y="5" width="18" height="14" rx="2"></rect>
            <circle cx="8" cy="11" r="2"></circle>
            <path d="M13 10h5"></path>
            <path d="M13 14h4"></path>
        </svg>

        IDs & Credentials

    </a>


    {{-- Documents & Certifications --}}
    <a
    href="{{ route('admin.documents.index') }}"
    @if(request()->routeIs('admin.documents.*'))
        aria-current="page"
    @endif
    class="mt-1 flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition
    {{ request()->routeIs('admin.documents.*')
        ? 'bg-[#012877] text-white shadow-sm'
        : 'text-slate-600 hover:bg-slate-50 hover:text-[#012877]' }}"
>

    <svg
        aria-hidden="true"
        class="w-[18px] h-[18px] shrink-0"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        viewBox="0 0 24 24"
    >
        <path d="M6 3h9l3 3v15H6z"></path>
        <path d="M15 3v4h4"></path>
        <path d="M9 12h6"></path>
        <path d="M9 16h6"></path>
    </svg>

    Documents & Certifications

</a>

</div>



                {{-- ADMINISTRATION --}}
                @can('manage-admins')

                    <div class="mb-7">

                        <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                            Administration
                        </p>


                        {{-- Admin Users --}}
                        <a
                            href="{{ route('admin.accounts.index') }}"
                            @if(request()->routeIs('admin.accounts.*'))
                                aria-current="page"
                            @endif
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition
                            {{ request()->routeIs('admin.accounts.*')
                                ? 'bg-[#012877] text-white shadow-sm'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-[#012877]' }}"
                        >

                            <svg
                                aria-hidden="true"
                                class="w-[18px] h-[18px] shrink-0"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                viewBox="0 0 24 24"
                            >
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>

                            Admin Users

                        </a>

                        {{-- Audit Logs --}}
<a
    href="{{ route('admin.audit-logs.index') }}"
    @if(request()->routeIs('admin.audit-logs.*'))
        aria-current="page"
    @endif
    class="mt-1 flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition
    {{ request()->routeIs('admin.audit-logs.*')
        ? 'bg-[#012877] text-white shadow-sm'
        : 'text-slate-600 hover:bg-slate-50 hover:text-[#012877]' }}"
>

    <svg
        aria-hidden="true"
        class="w-[18px] h-[18px] shrink-0"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        viewBox="0 0 24 24"
    >
        <path d="M9 5h6"></path>
        <path d="M9 9h6"></path>
        <path d="M9 13h4"></path>
        <path d="M5 3h14v18H5z"></path>
    </svg>

    Audit Logs

</a>

                    </div>

                @endcan

            </nav>



            {{-- ========================================================= --}}
            {{-- USER AREA --}}
            {{-- ========================================================= --}}

            <div class="border-t border-slate-100 p-4">

                <div class="flex items-center gap-3 px-2 py-2">

                    <div
                        class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
                        style="background-color: #E8EEF9;"
                    >

                        <span
                            class="text-sm font-bold"
                            style="color: #012877;"
                        >
                            {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                        </span>

                    </div>


                    <div class="min-w-0">

                        <p class="truncate text-sm font-semibold text-slate-800">
                            {{ auth()->user()->name }}
                        </p>

                        <p class="truncate text-xs text-slate-400">
                            {{ \App\Models\User::CMS_ROLES[auth()->user()->role] ?? 'Account' }}
                        </p>

                    </div>

                </div>

            </div>



            {{-- ACCOUNT --}}

            <div class="px-3 pb-5">

                <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    Account
                </p>


                <a
                    href="{{ route('admin.password.edit') }}"
                    @if(request()->routeIs('admin.password.edit'))
                        aria-current="page"
                    @endif
                    class="block rounded-lg px-3 py-2.5 text-sm font-medium transition
                    {{ request()->routeIs('admin.password.edit')
                        ? 'bg-[#012877] text-white'
                        : 'text-slate-600 hover:bg-slate-50 hover:text-[#012877]' }}"
                >
                    Change Password
                </a>


                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-[#012877]"
                    >
                        Log Out
                    </button>

                </form>

            </div>

        </aside>



        {{-- ========================================================= --}}
        {{-- MAIN CONTENT --}}
        {{-- ========================================================= --}}

        <div class="flex-1 min-w-0 flex flex-col">


            {{-- TOP BAR --}}

            <header class="h-16 bg-white border-b border-slate-200 flex items-center px-4 sm:px-6 md:px-8 shrink-0">

                <div class="flex items-center justify-between w-full">

                    <div>

                        <h2 class="text-sm font-semibold text-slate-800">
                            @yield('page_title', 'Dashboard')
                        </h2>

                    </div>

                    <div class="text-xs text-slate-400">
                        IDireksyon
                    </div>

                </div>

            </header>



            {{-- ========================================================= --}}
            {{-- TOAST NOTIFICATIONS --}}
            {{-- ========================================================= --}}

            <div
                class="pointer-events-none fixed right-4 top-4 z-50 flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3 sm:right-6 sm:top-6"
            >

                {{-- SUCCESS TOAST --}}
                @if(session('success'))

                    <div
                        x-data="{ show: true }"
                        x-show="show"
                        x-init="setTimeout(() => show = false, 3000)"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-2"
                        class="pointer-events-auto rounded-xl border border-emerald-200 bg-white p-4 shadow-xl"
                        role="status"
                    >

                        <div class="flex items-start gap-3">

                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">

                                <svg
                                    aria-hidden="true"
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="m5 12 4 4L19 6"></path>
                                </svg>

                            </div>


                            <div class="min-w-0 flex-1">

                                <p class="text-sm font-semibold text-slate-900">
                                    Success
                                </p>

                                <p class="mt-1 text-sm leading-5 text-slate-600">
                                    {{ session('success') }}
                                </p>

                            </div>


                            <button
                                type="button"
                                @click="show = false"
                                class="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Close notification"
                            >

                                <svg
                                    aria-hidden="true"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M6 6l12 12"></path>
                                    <path d="M18 6 6 18"></path>
                                </svg>

                            </button>

                        </div>

                    </div>

                @endif



                {{-- ERROR TOAST --}}
                @if(session('error'))

                    <div
                        x-data="{ show: true }"
                        x-show="show"
                        x-init="setTimeout(() => show = false, 4000)"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-2"
                        class="pointer-events-auto rounded-xl border border-red-200 bg-white p-4 shadow-xl"
                        role="alert"
                    >

                        <div class="flex items-start gap-3">

                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700">

                                <svg
                                    aria-hidden="true"
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M12 8v5"></path>
                                    <path d="M12 16h.01"></path>
                                </svg>

                            </div>


                            <div class="min-w-0 flex-1">

                                <p class="text-sm font-semibold text-slate-900">
                                    Error
                                </p>

                                <p class="mt-1 text-sm leading-5 text-slate-600">
                                    {{ session('error') }}
                                </p>

                            </div>


                            <button
                                type="button"
                                @click="show = false"
                                class="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Close notification"
                            >

                                <svg
                                    aria-hidden="true"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M6 6l12 12"></path>
                                    <path d="M18 6 6 18"></path>
                                </svg>

                            </button>

                        </div>

                    </div>

                @endif

            </div>



            {{-- ========================================================= --}}
            {{-- PAGE CONTENT --}}
            {{-- ========================================================= --}}

            <main class="flex-1 p-4 sm:p-6 md:p-8">

                @yield('content')

            </main>

        </div>

    </div>

</body>

</html>