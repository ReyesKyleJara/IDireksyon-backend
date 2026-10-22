@extends('admin.layouts.app')
@section('title', 'Prerequisite | IDireksyon')
@section('page_title', 'Smart Sequencing Prerequisite')
@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <a class="text-sm underline" href="{{ route('admin.government-ids.prerequisites.index', $governmentId) }}">← Back to prerequisites</a>
    <h1 class="text-2xl font-bold">{{ $prerequisite->exists ? 'Edit' : 'Add' }} prerequisite for {{ $governmentId->name }}</h1>
    @if($errors->any())
        <ul class="rounded border border-red-300 bg-red-50 p-4 text-red-800" role="alert">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <form class="admin-panel space-y-5 p-6" method="POST"
        action="{{ $prerequisite->exists ? route('admin.government-ids.prerequisites.update', [$governmentId, $prerequisite]) : route('admin.government-ids.prerequisites.store', $governmentId) }}"
        x-data="{ kind: @js(old('prerequisite_document_id', $prerequisite->prerequisite_document_id) ? 'document' : 'government_id'), search: '' }">
        @csrf
        @if($prerequisite->exists) @method('PUT') @endif
        <fieldset class="space-y-3">
            <legend class="font-semibold">Requires Before Application</legend>
            <p class="text-sm text-slate-600">Choose one ID or Document that must be obtained first. Do not add acceptable alternatives here.</p>
            <label class="mr-4"><input type="radio" x-model="kind" value="government_id"> Government ID</label>
            <label><input type="radio" x-model="kind" value="document"> Document</label>
            <label class="block" for="prerequisite-search">Search existing IDs or Documents</label>
            <input id="prerequisite-search" type="search" class="admin-input" x-model="search" placeholder="Search by name">
            <div x-show="kind === 'government_id'">
                <label for="prerequisite-id" class="block">Government ID</label>
                <select id="prerequisite-id" name="prerequisite_government_id_id" class="admin-input" :disabled="kind !== 'government_id'" :required="kind === 'government_id'">
                    <option value="">Select a Government ID</option>
                    @foreach($ids as $id)
                        <option value="{{ $id->id }}" x-show="@js(mb_strtolower($id->name)).includes(search.toLowerCase())" @selected((string) old('prerequisite_government_id_id', $prerequisite->prerequisite_government_id_id) === (string) $id->id)>{{ $id->name }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="kind === 'document'" x-cloak>
                <label for="prerequisite-document" class="block">Document</label>
                <select id="prerequisite-document" name="prerequisite_document_id" class="admin-input" :disabled="kind !== 'document'" :required="kind === 'document'">
                    <option value="">Select a Document</option>
                    @foreach($documents as $document)
                        <option value="{{ $document->id }}" x-show="@js(mb_strtolower($document->name)).includes(search.toLowerCase())" @selected((string) old('prerequisite_document_id', $prerequisite->prerequisite_document_id) === (string) $document->id)>{{ $document->name }}</option>
                    @endforeach
                </select>
            </div>
        </fieldset>
        <div>
            <label for="profile" class="mb-2 block font-semibold">Applies To</label>
            <select id="profile" name="requirement_set_id" class="admin-input">
                <option value="">Everyone</option>
                @foreach($profiles as $profile)
                    @php($summary = \App\Support\ApplicabilityProfileSummary::forProfile($profile))
                    <option value="{{ $profile->id }}" @selected((string) old('requirement_set_id', $prerequisite->requirement_set_id) === (string) $profile->id)>{{ $summary['summary'] }}{{ $summary['needs_rule_setup'] ? ' · Needs rule setup' : '' }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-sm text-slate-600">Profiles marked Needs rule setup remain manually selectable. Automatic matching is disabled until structured rules are configured.</p>
        </div>
        <div>
            <label for="sort-order" class="block">Position</label>
            <input id="sort-order" class="admin-input" type="number" name="sort_order" min="0" max="4294967295" required value="{{ old('sort_order', $prerequisite->sort_order) }}">
            <p class="text-sm text-slate-600">Lower positions are considered first. Dependencies always precede the ID that needs them.</p>
        </div>
        <div>
            <label for="note" class="block">Maintainer rationale (optional)</label>
            <textarea id="note" name="note" class="admin-input" maxlength="500">{{ old('note', $prerequisite->note) }}</textarea>
        </div>
        <button class="admin-primary">Save prerequisite</button>
    </form>
</div>
@endsection
