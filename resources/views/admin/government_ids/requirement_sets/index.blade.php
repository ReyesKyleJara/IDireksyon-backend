@extends('admin.layouts.app')
@section('title', 'Applicability Profiles | IDireksyon')
@section('page_title', 'Applicability Profiles')
@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('admin.government-ids.show', $governmentId) }}" class="text-sm text-slate-500">← Back to ID details</a>
    <div class="mb-6 mt-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="admin-eyebrow mb-2">{{ $governmentId->name }}</p>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Applicability Profiles</h1>
            <p class="mt-2 text-sm text-slate-500">Define who each Requirements checklist and linked Application Guide applies to.</p>
        </div>
        <a href="{{ route('admin.government-ids.requirement-sets.create', $governmentId) }}" class="admin-primary">Add Applicability Profile</a>
    </div>
    <p class="mb-5 text-sm text-slate-500">Choose Manage Requirements to add the checklist for each applicant and application type.</p>
    <div class="space-y-4">
        @forelse($sets as $set)
            <article class="admin-panel flex flex-wrap items-center justify-between gap-4 p-6">
                <div class="space-y-3">
                    @include('admin.government_ids.partials.applicability-profile')
                    <p class="text-sm text-slate-600">Used by {{ $set->groups_count }} requirement groups · {{ $set->application_steps_count }} Application Guide steps · {{ $set->prerequisites_count }} sequencing prerequisites.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.government-ids.requirement-sets.groups.index', [$governmentId, $set]) }}" class="admin-primary">Manage Requirements</a>
                    <a href="{{ route('admin.government-ids.requirement-sets.edit', [$governmentId, $set]) }}" class="admin-secondary" aria-label="Edit {{ $set->display_label }}">Edit</a>
                    <form method="POST" action="{{ route('admin.government-ids.requirement-sets.destroy', [$governmentId, $set]) }}" onsubmit="return confirm('Delete this applicability profile and its linked requirement groups and items? The referenced IDs and Documents will remain. Profiles with guide steps or sequencing prerequisites cannot be deleted.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-secondary text-red-600" aria-label="Delete {{ $set->display_label }}">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="admin-panel p-8 text-center">
                <h2 class="font-semibold text-slate-900">No applicability profiles yet.</h2>
                <p class="mt-2 text-sm text-slate-500">Start with an application type and who it applies to.</p>
            </div>
        @endforelse
    </div>
    <div class="mt-6">{{ $sets->links() }}</div>
</div>
@endsection
