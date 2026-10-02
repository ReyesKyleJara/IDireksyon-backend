@extends('admin.layouts.app')

@section('title', 'Dashboard | IDireksyon')
@section('page_title', 'Dashboard')

@section('content')
@php
    $summaries = [
        ['label' => 'Government IDs', 'count' => $governmentIdCount, 'description' => 'IDs and credentials in the directory', 'route' => 'admin.government-ids.index', 'icon' => 'id'],
        ['label' => 'Documents', 'count' => $documentCount, 'description' => 'Documents and certifications recorded', 'route' => 'admin.documents.index', 'icon' => 'document'],
        ['label' => 'Offices', 'count' => $officeCount, 'description' => 'Office records, including drafts', 'route' => 'admin.offices.index', 'icon' => 'office'],
    ];
@endphp
<div class="mx-auto max-w-7xl space-y-7">
    <section class="relative overflow-hidden rounded-2xl bg-[#012877] p-6 text-white sm:p-8">
        <div aria-hidden="true" class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full border border-white/10"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-28 right-12 h-64 w-64 rounded-full border border-white/10"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-xl">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-blue-200">IDireksyon · Research CMS</p>
                <h1 class="break-words text-2xl font-bold tracking-tight sm:text-3xl">Welcome back, {{ auth()->user()->name }}.</h1>
                <p class="mt-3 text-sm leading-6 text-blue-100">Keep useful, well-researched information within reach of Santa Maria residents.</p>
            </div>
            <a href="{{ route('admin.government-ids.create') }}"
                class="inline-flex shrink-0 items-center justify-center gap-2 self-start rounded-lg bg-white px-4 py-3 text-sm font-semibold text-[#012877] shadow-sm transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white lg:self-center">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" /></svg>
                Add Government ID
            </a>
        </div>
    </section>

    <section aria-labelledby="directory-overview">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 id="directory-overview" class="text-base font-semibold text-slate-900">Your directory at a glance</h2>
            <span class="text-xs text-slate-500">Current record totals</span>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach($summaries as $summary)
                <a href="{{ route($summary['route']) }}" class="group admin-panel block p-5 transition hover:border-blue-300 hover:shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#012877] sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-[#012877]">
                            <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                @if($summary['icon'] === 'id')
                                    <rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="8" cy="11" r="2" /><path d="M5 16c0-3 6-3 6 0M14 10h4M14 14h4" />
                                @elseif($summary['icon'] === 'document')
                                    <path d="M6 3h9l3 3v15H6zM14 3v5h4M9 12h6M9 16h6" />
                                @else
                                    <path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" />
                                @endif
                            </svg>
                        </span>
                        <svg aria-hidden="true" class="h-4 w-4 text-slate-400 transition group-hover:text-[#012877]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                    </div>
                    <p class="mt-5 text-3xl font-bold tracking-tight text-slate-900 tabular-nums">{{ number_format($summary['count']) }}</p>
                    <h3 class="mt-1 text-sm font-semibold text-slate-800">{{ $summary['label'] }}</h3>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ $summary['description'] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    <div class="grid items-start gap-6 lg:grid-cols-3">
        <section class="admin-panel overflow-hidden lg:col-span-2" aria-labelledby="recent-ids">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
                <div>
                    <h2 id="recent-ids" class="text-base font-semibold text-slate-900">Recently updated IDs</h2>
                    <p class="mt-1 text-xs text-slate-500">Pick up where your research left off.</p>
                </div>
                <a href="{{ route('admin.government-ids.index') }}" class="rounded text-sm font-semibold text-[#012877] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#012877]">View directory →</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse($recentGovernmentIds as $governmentId)
                    <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <a href="{{ route('admin.government-ids.show', $governmentId) }}" class="break-words text-sm font-semibold text-slate-900 hover:text-[#012877] hover:underline">{{ $governmentId->name }}</a>
                            <p class="mt-1 break-words text-xs text-slate-500">{{ $governmentId->agency?->name ?? 'Agency not yet assigned' }}</p>
                            <p class="mt-2 text-xs text-slate-500">
                                Last edited:
                                @if($governmentId->updated_at)
                                    <time datetime="{{ $governmentId->updated_at->toIso8601String() }}">{{ $governmentId->updated_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT</time>
                                @else
                                    Not recorded
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('admin.government-ids.edit', $governmentId) }}" class="admin-secondary shrink-0 self-start sm:self-center" aria-label="Edit {{ $governmentId->name }}">Edit</a>
                    </li>
                @empty
                    <li class="px-6 py-12 text-center">
                        <p class="text-sm font-semibold text-slate-800">Your ID directory starts here</p>
                        <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500">Add your first government ID to begin organizing its requirements and application information.</p>
                        <a href="{{ route('admin.government-ids.create') }}" class="admin-primary mt-5">Add Government ID</a>
                    </li>
                @endforelse
            </ul>
            @if($recentGovernmentIds->isNotEmpty())
                <p class="border-t border-slate-100 bg-slate-50 px-5 py-3 text-xs leading-5 text-slate-500 sm:px-6">Editing a record does not mark its research as verified.</p>
            @endif
        </section>

        <section class="admin-panel p-5 sm:p-6" aria-labelledby="quick-actions">
            <h2 id="quick-actions" class="text-base font-semibold text-slate-900">Quick actions</h2>
            <p class="mt-1 text-xs text-slate-500">Make room for your next research finding.</p>
            <div class="mt-5 space-y-3">
                <a href="{{ route('admin.documents.create') }}" class="group block rounded-xl border border-slate-200 p-4 transition hover:border-blue-300 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#012877]">
                    <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold text-slate-800">Add Document</h3><span aria-hidden="true" class="text-[#012877]">+</span></div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Record a supporting document or certification.</p>
                </a>
                <a href="{{ route('admin.offices.create') }}" class="group block rounded-xl border border-slate-200 p-4 transition hover:border-blue-300 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#012877]">
                    <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold text-slate-800">Add Office</h3><span aria-hidden="true" class="text-[#012877]">+</span></div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Start a branch draft and fill in details as you research.</p>
                </a>
            </div>
        </section>
    </div>
</div>
@endsection
