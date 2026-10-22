@extends('admin.layouts.app')
@section('title', 'Smart Sequencing Prerequisites | IDireksyon')
@section('page_title', 'Smart Sequencing Prerequisites')
@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <a class="text-sm underline" href="{{ route('admin.government-ids.show', $governmentId) }}">← Back to {{ $governmentId->name }}</a>
    <h1 class="text-2xl font-bold">Smart Sequencing Prerequisites</h1>
    <p>Configure only things the applicant must obtain before applying for {{ $governmentId->name }}. Acceptable evidence and OR alternatives belong in Requirements.</p>
    <a class="admin-primary" href="{{ route('admin.government-ids.prerequisites.create', $governmentId) }}">Add prerequisite</a>
    @forelse($prerequisites as $prerequisite)
        <article class="admin-panel space-y-3 p-6">
            <p class="text-sm text-slate-500">Requires Before Application</p>
            <h2 class="font-semibold">{{ ($prerequisite->prerequisiteGovernmentId ?? $prerequisite->prerequisiteDocument)->name }}</h2>
            <p class="text-sm">{{ $prerequisite->prerequisite_government_id_id ? 'Government ID' : 'Document' }} · Position {{ $prerequisite->sort_order }}</p>
            @if($prerequisite->profile)
                @include('admin.government_ids.partials.applicability-profile', ['set' => $prerequisite->profile])
            @else
                <p class="text-sm font-semibold">Applies To</p>
                <p>Everyone</p>
            @endif
            @if($prerequisite->note)<p class="text-sm text-slate-600">{{ $prerequisite->note }}</p>@endif
            <div class="flex gap-3">
                <a class="admin-secondary" href="{{ route('admin.government-ids.prerequisites.edit', [$governmentId, $prerequisite]) }}">Edit prerequisite</a>
                <form method="POST" action="{{ route('admin.government-ids.prerequisites.destroy', [$governmentId, $prerequisite]) }}" onsubmit="return confirm('Remove this prerequisite link? The referenced ID or Document will remain.');">
                    @csrf @method('DELETE')
                    <button class="admin-secondary text-red-600">Remove prerequisite</button>
                </form>
            </div>
        </article>
    @empty
        <p class="admin-panel p-6">No explicit prerequisites configured. No dependencies will be inferred from Requirements.</p>
    @endforelse
</div>
@endsection
