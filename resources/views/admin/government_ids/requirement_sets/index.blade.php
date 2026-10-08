@extends('admin.layouts.app')
@section('title', 'Requirement Sets | IDireksyon')
@section('page_title', 'Requirement Sets')
@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('admin.government-ids.show', $governmentId) }}" class="text-sm text-slate-500">← Back to ID details</a>
    <div class="mb-6 mt-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="admin-eyebrow mb-2">{{ $governmentId->name }}</p>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Requirement Sets</h1>
            <p class="mt-2 text-sm text-slate-500">Organize checklists by application and applicant type.</p>
        </div>
        <a href="{{ route('admin.government-ids.requirement-sets.create', $governmentId) }}" class="admin-primary">Add Requirement Set</a>
    </div>
    <p class="mb-5 text-sm text-slate-500">Choose Manage Requirements to add the checklist for each applicant and application type.</p>
    <div class="space-y-4">
        @forelse($sets as $set)
            <article class="admin-panel flex flex-wrap items-center justify-between gap-4 p-6">
                <h2 class="break-words text-base font-semibold text-slate-900">{{ $set->display_label }}</h2>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.government-ids.requirement-sets.groups.index', [$governmentId, $set]) }}" class="admin-primary">Manage Requirements</a>
                    <a href="{{ route('admin.government-ids.requirement-sets.edit', [$governmentId, $set]) }}" class="admin-secondary" aria-label="Edit {{ $set->display_label }}">Edit</a>
                    <form method="POST" action="{{ route('admin.government-ids.requirement-sets.destroy', [$governmentId, $set]) }}" onsubmit="return confirm('Delete this requirement set and all its groups and items? The referenced IDs and Documents will remain.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-secondary text-red-600" aria-label="Delete {{ $set->display_label }}">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="admin-panel p-8 text-center">
                <h2 class="font-semibold text-slate-900">No requirement sets yet.</h2>
                <p class="mt-2 text-sm text-slate-500">Start with an application type and who it applies to.</p>
            </div>
        @endforelse
    </div>
    <div class="mt-6">{{ $sets->links() }}</div>
</div>
@endsection
