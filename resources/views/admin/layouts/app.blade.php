<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'IDireksyon Admin Panel')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-50 text-slate-900">

    <div class="min-h-screen flex flex-col md:flex-row" x-data="{ navigationOpen: false }">

        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 md:hidden">
            <span class="text-sm font-semibold text-[#012877]">IDireksyon Admin Panel</span>
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



            {{-- NAVIGATION --}}
            <nav aria-label="Admin navigation" class="flex-1 px-3 pb-6 overflow-y-auto">


                @can('manage-admins')
                {{-- OVERVIEW --}}
                <div class="mb-7">

                    <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        Overview
                    </p>


                    {{-- Dashboard --}}
                    <a
                        href="{{ route('admin.dashboard') }}"
                        @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif
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



                @endcan

                {{-- CONTENT --}}
                <div class="mb-7">

                    <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        Content
                    </p>


                    {{-- Government IDs --}}
                    <a
                        href="{{ route('admin.government-ids.index') }}"
                        @if(request()->routeIs('admin.government-ids.*')) aria-current="page" @endif
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

                        Government IDs

                    </a>


                    {{-- Documents --}}
                    <a href="{{ route('admin.documents.index') }}"
                        @if(request()->routeIs('admin.documents.*')) aria-current="page" @endif
                        class="mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.documents.*') ? 'bg-[#012877] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-[#012877]' }}">
                        <x-admin.catalog-icon :document="true" class="h-[18px] w-[18px] shrink-0" />
                        Documents
                    </a>

                </div>
                @can('manage-reference-data')
                <div class="mb-7">
                    <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Reference Data</p>
                    @foreach(['levels'=>'Levels','categories'=>'Categories','agencies'=>'Agencies','barangays'=>'Barangays','offices'=>'Offices'] as $key=>$name)
                        <a href="{{ route('admin.references.index',$key) }}" @if(request()->route('type') === $key) aria-current="page" @endif class="mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->route('type')===$key ? 'bg-[#012877] text-white' : 'text-slate-600 hover:bg-slate-50' }}"><x-admin.catalog-icon :document="true" class="h-[18px] w-[18px] shrink-0" />{{ $name }}</a>
                    @endforeach
                </div>
                @endcan

                @can('manage-admins')
                {{-- SYSTEM --}}
                <div>

                    <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        Administration
                    </p>


                    {{-- Admin Users --}}
                    <a
                        href="{{ route('admin.accounts.index') }}"
                        @if(request()->routeIs('admin.accounts.*')) aria-current="page" @endif
                        class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.accounts.*') ? 'bg-[#012877] text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-[#012877]' }}"
                    >

                        <div class="flex items-center gap-3">

                            <svg
                            aria-hidden="true"
                                class="w-[18px] h-[18px] shrink-0"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                viewBox="0 0 24 24"
                            >
                                <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path>
                                <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.42 1.42-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-2v-.48a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-1.42-1.42.06-.06A1.7 1.7 0 0 0 9.4 15a1.7 1.7 0 0 0-1.56-1.03H7v-2h.84A1.7 1.7 0 0 0 9.4 10.9a1.7 1.7 0 0 0-.34-1.88L9 8.96l1.42-1.42.06.06a1.7 1.7 0 0 0 1.88.34A1.7 1.7 0 0 0 13.39 6.4V6h2v.4a1.7 1.7 0 0 0 1.03 1.54 1.7 1.7 0 0 0 1.88-.34l.06-.06 1.42 1.42-.06.06a1.7 1.7 0 0 0-.34 1.88A1.7 1.7 0 0 0 20.93 12H21v2h-.07A1.7 1.7 0 0 0 19.4 15Z"></path>
                            </svg>

                            Admin Users

                        </div>


                        {{-- Super Admin indicator --}}
                        <span class="text-[10px] font-semibold text-slate-400">
                            SUPER
                        </span>

                    </a>

                    <a href="{{ route('admin.audit-logs.index') }}" @if(request()->routeIs('admin.audit-logs.*')) aria-current="page" @endif class="mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('admin.audit-logs.*') ? 'bg-[#012877] text-white' : 'text-slate-600 hover:bg-slate-50' }}"><x-admin.catalog-icon :document="true" class="h-[18px] w-[18px] shrink-0" />Audit Logs</a>
                </div>

                @endcan

            </nav>



            {{-- USER AREA --}}
            <div class="p-4 border-t border-slate-100">

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

                        <p class="text-sm font-semibold text-slate-800 truncate">
                            {{ auth()->user()->name }}
                        </p>

                        <p class="text-xs text-slate-400 truncate">
                            {{ \App\Models\User::CMS_ROLES[auth()->user()->role] ?? 'Account' }}
                        </p>

                    </div>

                </div>

            </div>

            <div class="px-3 pb-5">
                <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Account</p>
                <a href="{{ route('admin.password.edit') }}" @if(request()->routeIs('admin.password.edit')) aria-current="page" @endif class="block rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('admin.password.edit') ? 'bg-[#012877] text-white' : 'text-slate-600 hover:bg-slate-50' }}">Change Password</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-[#012877]">Log Out</button></form>
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



            {{-- PAGE CONTENT --}}
            <main class="flex-1 p-4 sm:p-6 md:p-8">

                @if(session('success'))
                    <div role="status" class="mx-auto mb-5 max-w-7xl rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div role="alert" class="mx-auto mb-5 max-w-7xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
                @endif
                @yield('content')

            </main>

        </div>

    </div>

</body>

</html>
