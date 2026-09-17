@extends('admin.layouts.app')
@section('title', $record->name.' | IDireksyon Admin Panel')
@section('page_title', $plural)
@section('content')
<div class="mx-auto max-w-6xl">
    <a href="{{ route('admin.'.$resource.'.index') }}" class="text-sm text-slate-500 hover:text-[#012877]">&larr; {{ $plural }}</a>
    <header class="admin-panel mb-6 mt-5 overflow-hidden">
        <div class="flex flex-col gap-5 p-5 sm:p-7 md:flex-row md:items-center md:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-[#012877]"><x-admin.catalog-icon :document="$isDocument" class="h-9 w-9" /></div>
                <div class="min-w-0">
                    <p class="admin-eyebrow mb-2">{{ $label }}</p>
                    <h1 class="break-words text-2xl font-bold tracking-tight sm:text-3xl">{{ $record->name }}</h1>
                    <p class="mt-2 break-words text-sm text-slate-500">{{ $record->agency_name ?: 'Issuing agency / office not yet added' }}</p>
                </div>
            </div>
            <a href="{{ route('admin.'.$resource.'.edit', $record) }}" class="admin-primary shrink-0">Edit information</a>
        </div>
        <div class="flex flex-wrap gap-x-6 gap-y-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3 text-xs text-slate-500 sm:px-7">
            <span>Last edited: {{ $record->last_updated?->format('M j, Y, g:i a') ?? 'Not recorded' }}</span>
            <span>Source checked: {{ $record->source_checked_at?->format('M j, Y') ?? 'Not recorded' }}</span>
        </div>
    </header>
    <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
        <p class="font-semibold">{{ $researchStages[$record->research_status] ?? 'Needs research' }} · {{ $availabilityStates[$record->availability_status] ?? 'Issuance not confirmed' }}</p>
        <p class="mt-1">{{ $record->level?->name ?? 'Level pending' }} · {{ $record->category?->display_name ?? 'Category pending' }} · {{ $recordTypes[$record->record_type] ?? 'Type pending' }}</p>
        @if($record->research_notes)<p class="mt-2 whitespace-pre-line break-words">{{ $record->research_notes }}</p>@endif
        <p class="mt-2 text-xs">This CMS entry does not automatically publish a service or confirm new issuance. Existing-card validity and new-card availability are different.</p>
    </section>
    @include('admin.catalog.readiness')
    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
        <div class="min-w-0 space-y-6">
            <section class="admin-panel p-5 sm:p-7" id="about">
                <h2 class="text-lg font-semibold">About this {{ strtolower($label) }}</h2>
                <dl class="mt-5 space-y-5 text-sm">
                    @foreach(['description' => 'Description', 'purpose' => 'Purpose', 'validity' => $isDocument ? 'Validity / acceptance notes' : 'Validity period'] as $field => $fieldLabel)
                        <div><dt class="mb-1 font-medium text-slate-800">{{ $fieldLabel }}</dt><dd class="whitespace-pre-line break-words leading-6 text-slate-500">{{ $record->$field ?: 'Not yet added.' }}</dd></div>
                    @endforeach
                </dl>
            </section>
            <section class="admin-panel p-5 sm:p-7" id="requirements">
                <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-semibold">{{ $isDocument ? 'Requirements to obtain this document' : 'Application requirements' }}</h2><span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ $record->requirements->count() }}</span></div>
                <div class="mt-5 space-y-3">
                    @foreach($record->requirementGroups as $group)
                        <div class="rounded-lg bg-slate-50 p-3 text-sm"><p class="font-medium">{{ $group->name }} — {{ $group->rule_label }}</p>@if($group->condition_notes)<p class="mt-1 text-slate-500">Applies when: {{ $group->condition_notes }} (manual review)</p>@endif</div>
                    @endforeach
                    @forelse($record->requirements as $requirement)
                        @php
                            $reference = $requirement->type === 'government_id' ? $requirement->referencedGovernmentId : ($requirement->type === 'document' ? $requirement->referencedDocument : null);
                            $referenceResource = $requirement->type === 'government_id' ? 'government-ids' : 'documents';
                        @endphp
                        <div class="rounded-lg border border-slate-200 p-4">
                            @if($reference)<a href="{{ route('admin.'.$referenceResource.'.show', $reference) }}" class="break-words text-sm font-semibold text-[#012877] hover:underline">{{ $requirement->display_name }}</a>
                            @else<p class="break-words text-sm font-semibold">{{ $requirement->display_name }}</p>@endif
                            <p class="mt-1 text-xs text-slate-500">{{ $requirement->group ? $requirement->group->name.' · '.$requirement->group->rule_label : 'Ungrouped — needs review' }}{{ $requirement->is_dependency ? ' · Obtain first if missing' : '' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $requirement->type === 'custom' ? 'Other requirement' : ucfirst(str_replace('_', ' ', $requirement->type)) }}</p>
                            @if($requirement->description)<p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-500">{{ $requirement->description }}</p>@endif
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-300 p-5 text-sm leading-6 text-slate-500">No requirements recorded yet. This does not mean no requirements are needed.</p>
                    @endforelse
                </div>
                <a class="mt-4 inline-block text-sm font-medium text-[#012877] hover:underline" href="{{ route('admin.'.$resource.'.edit', $record) }}#requirements">Manage requirements &rarr;</a>
            </section>
            <section class="admin-panel p-5 sm:p-7" id="process">
                <h2 class="text-lg font-semibold">{{ $isDocument ? 'Request process' : 'Application process' }}</h2>
                <ol class="mt-5 space-y-5">
                    @forelse($record->application_steps ?? [] as $step)
                        <li class="flex gap-4"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-[#012877]">{{ $loop->iteration }}</span><div class="min-w-0"><h3 class="break-words text-sm font-semibold">{{ $step['title'] }}</h3>@if($step['details'] ?? null)<p class="mt-1 whitespace-pre-line break-words text-sm leading-6 text-slate-500">{{ $step['details'] }}</p>@endif</div></li>
                    @empty
                        <li class="text-sm text-slate-500">Steps have not been added yet.</li>
                    @endforelse
                </ol>
            </section>
            <section class="admin-panel p-5 sm:p-7" id="costs">
                <h2 class="text-lg font-semibold">{{ $isDocument ? 'Issuance / request fees' : 'Application fees' }}</h2>
                <p class="mt-4 whitespace-pre-line break-words text-sm leading-6 text-slate-500">{{ $record->cost_notes ?: ($record->fees->isEmpty() ? 'Fee information has not been recorded. Do not assume this service is free.' : 'Optional charges and alternative choices are listed separately; no total is assumed.') }}</p>
                @if($record->fees->isNotEmpty())
                    <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-slate-200"><th class="py-3 pr-3">Fee</th><th class="py-3 pr-3">Amount</th><th class="py-3">Charge type</th></tr></thead><tbody>
                    @foreach($record->fees as $fee)<tr class="border-b border-slate-100"><td class="py-3 pr-3">{{ $fee->name }}@if($fee->notes)<small class="mt-1 block whitespace-pre-line text-slate-500">{{ $fee->notes }}</small>@endif</td><td class="whitespace-nowrap py-3 pr-3">{{ $fee->amount === null ? 'Unknown' : '₱'.number_format((float)$fee->amount,2) }}</td><td class="py-3">{{ \App\Models\CatalogFee::KINDS[$fee->kind] ?? $fee->kind }}@if($fee->choice_group)<small class="block text-slate-500">{{ $fee->choice_group }}</small>@endif</td></tr>@endforeach
                    </tbody></table></div>
                @endif
            </section>
        </div>
        <aside class="space-y-5">
            <section class="admin-panel p-5">
                <h2 class="text-sm font-semibold">Official source</h2>
                @if($record->source_url && in_array(strtolower(parse_url($record->source_url, PHP_URL_SCHEME) ?? ''), ['http', 'https']))
                    <a href="{{ $record->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 block break-all text-sm text-[#012877] underline">{{ $record->source_url }}<span class="sr-only"> (opens in a new tab)</span></a>
                @else<p class="mt-3 text-sm text-slate-500">No source added yet.</p>@endif
                <p class="mt-4 text-xs leading-5 text-slate-500">Source checked: {{ $record->source_checked_at?->format('M j, Y') ?? 'Not recorded' }}. Last edited and source checked are separate dates.</p>
            </section>
            <section class="admin-panel p-5">
                <h2 class="text-sm font-semibold">Linked service offices</h2>
                @forelse($record->offices as $office)@can('manage-reference-data')<a href="{{ route('admin.references.edit',['offices',$office->id]) }}" class="mt-3 block text-sm text-[#012877] underline">{{ $office->name }}</a>@else<p class="mt-3 text-sm text-slate-600">{{ $office->name }}</p>@endcan @empty<p class="mt-3 text-sm text-slate-500">No office links researched yet.</p>@endforelse
            </section>
            <section class="admin-panel p-5">
                <h2 class="text-sm font-semibold">On this page</h2>
                <nav aria-label="Entry sections" class="mt-3 flex flex-col gap-3 text-sm text-slate-500">
                    <a href="#about" class="hover:text-[#012877]">About</a><a href="#requirements" class="hover:text-[#012877]">Requirements</a><a href="#process" class="hover:text-[#012877]">{{ $isDocument ? 'Request process' : 'Application process' }}</a><a href="#costs" class="hover:text-[#012877]">Fees</a>
                </nav>
            </section>
            <p class="px-1 text-xs leading-5 text-slate-500">Research content. Check the source and applicable conditions before using this information in resident guidance.</p>
        </aside>
    </div>
    <details class="mt-8 rounded-xl border border-red-200 bg-white p-5">
        <summary class="cursor-pointer text-sm font-semibold text-red-700">Danger zone</summary>
        <p class="mt-4 text-sm text-slate-500">Permanently delete this {{ strtolower($label) }}. Entries used in other applications must be unlinked first.</p>
        <form method="POST" action="{{ route('admin.'.$resource.'.destroy', $record) }}" class="mt-4" onsubmit="return confirm('Permanently delete this entry? This cannot be undone.');">
            @csrf @method('DELETE')
            <button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50" type="submit">Delete {{ $label }}</button>
        </form>
    </details>
</div>
@endsection
