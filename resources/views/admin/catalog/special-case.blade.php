@extends('admin.layouts.app')
@section('title', 'Special case guide | IDireksyon')
@section('page_title', 'Special case guide')
@section('content')
@include('admin.government_ids.partials.editor-styles')
@php
    $recordKind = $governmentId instanceof \App\Models\Document ? 'document' : 'government_id';
    $scenario = ['application_type' => 'new', 'applicant_type' => 'all'];
    $checklists = [['client_key' => 'special-case', ...$scenario, 'groups' => $case['requirements'] ?? []]];
    $guideRows = [[...$scenario, 'steps' => $case['steps'] ?? []]];
    $guideConfig = ['wizard' => false, 'deferred' => false, 'serializeAll' => true, 'singleScenario' => true, 'scenarios' => $guideRows,
        'oldPayload' => old('application_guide_payload'), 'stepTypes' => \App\Models\GovernmentIdApplicationStep::TYPES,
        'requirementsByScenario' => [], 'previewReferences' => []];
    $backUrl = route($recordRoutes.'.edit-wizard.show', [$governmentId, 'guide']);
    $sourceRows = old('sources', $case['sources'] ?? []);
    if (!$sourceRows) $sourceRows = [['title' => 'Official reference', 'url' => '', 'checked_on' => '', 'scope' => '', 'published_on' => '', 'section' => '']];
    $openAdvanced = collect(array_keys($errors->getMessages()))->contains(fn ($field) => preg_match('/^(requirements|application_guide|sources|research_metadata\.sources|fee_ids|office_ids|assistance_|before_you_begin|action_label)/', $field));
    $hasSavedDetails = !empty($case['requirements']) || !empty($case['steps']) || !empty($case['before_you_begin']) || !empty($case['fee_ids']) || !empty($case['office_ids']) || count($case['sources'] ?? []) > 1 || filled($case['assistance_title'] ?? null) || filled($case['assistance_description'] ?? null) || (filled($case['action_label'] ?? null) && $case['action_label'] !== 'View guide');
@endphp
<div class="id-editor mx-auto max-w-4xl space-y-5">
    <a class="text-sm text-slate-500" href="{{ $backUrl }}">← Back to {{ $governmentId->name }}</a>
    <header><h1 class="text-2xl font-semibold">{{ isset($case['id']) ? 'Edit special case guide' : 'Add special case guide' }}</h1><p class="mt-1 text-sm text-slate-500">{{ $governmentId->name }}</p></header>
    <form method="POST" action="{{ route($recordRoutes.'.special-cases.save', $governmentId) }}" class="space-y-5"
        x-data="{ advanced: @js($openAdvanced), status: @js(old('status', $case['status'] ?? 'draft')) }" @invalid.capture="if ($event.target.closest('#special-case-details')) advanced = true">
        @csrf
        <input type="hidden" name="simplified_editor" value="1">
        <input type="hidden" name="advanced_content_present" value="1">
        <input type="hidden" name="_catalog_revision" value="{{ old('_catalog_revision', \App\Services\CatalogRevision::token($governmentId)) }}">
        @if(isset($case['id']))<input type="hidden" name="case_id" value="{{ $case['id'] }}">@endif
        @if($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <section class="admin-panel space-y-5 p-5 sm:p-6" aria-label="Special case guide details">
            <label class="block text-sm font-medium">Special Case Title <span class="text-red-600" aria-hidden="true">*</span>
                <input class="admin-input mt-2" name="title" required maxlength="255" value="{{ old('title', $case['title'] ?? '') }}" placeholder="e.g. Applying through a representative">
            </label>
            <label class="block text-sm font-medium">When Does This Apply?
                <textarea class="admin-input mt-2" name="description" maxlength="2000" rows="2" placeholder="Who needs this guide, or when does this situation apply?">{{ old('description', $case['description'] ?? '') }}</textarea>
            </label>
            <label class="block text-sm font-medium">What Should the Applicant Do?
                <textarea class="admin-input mt-2" name="introduction" maxlength="10000" rows="5" placeholder="Explain the practical actions the applicant should take.">{{ old('introduction', $case['introduction'] ?? '') }}</textarea>
            </label>
            <label class="block text-sm font-medium">Additional Information <span class="font-normal text-slate-400">(optional)</span>
                <textarea class="admin-input mt-2" name="notes" maxlength="20000" rows="3" placeholder="Supporting documents, reminders, exceptions, or other helpful details.">{{ old('notes', $case['notes'] ?? '') }}</textarea>
            </label>
            <label class="block text-sm font-medium">Official Source <span class="font-normal text-slate-400">(optional)</span>
                <input type="url" class="admin-input mt-2" name="official_source" maxlength="2048" value="{{ old('official_source', $case['sources'][0]['url'] ?? '') }}" placeholder="https://">
            </label>
            <fieldset class="space-y-2"><legend class="text-sm font-medium">Research status</legend><div class="flex flex-wrap gap-4">
                @foreach(['draft' => 'Draft', 'needs_research' => 'Needs research', 'reviewed' => 'Reviewed'] as $value => $label)
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="radio" name="status" value="{{ $value }}" x-model="status" @checked(old('status', $case['status'] ?? 'draft') === $value)> {{ $label }}</label>
                @endforeach
            </div></fieldset>
            <label class="block text-sm" x-cloak x-show="status === 'reviewed' || @js(filled(old('source_checked_on', $case['sources'][0]['checked_on'] ?? null)))">Source verification date
                <input class="admin-input mt-2" type="date" name="source_checked_on" max="{{ now('Asia/Manila')->toDateString() }}" value="{{ old('source_checked_on', $case['sources'][0]['checked_on'] ?? '') }}">
                <span class="mt-1 block text-xs text-slate-500">Reviewed guides need an official source and its verification date.</span>
            </label>
        </section>
        <button type="button" class="text-sm font-medium text-blue-900" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="special-case-details">
            <span x-text="advanced ? 'Hide additional details' : @js($hasSavedDetails ? 'Edit additional saved content' : 'Additional details (optional)')">{{ $hasSavedDetails ? 'Edit additional saved content' : 'Additional details (optional)' }}</span>
        </button>
        <div id="special-case-details" x-cloak x-show="advanced" class="space-y-5">
            @if(filled($case['before_you_begin'] ?? null) || old('before_you_begin') !== null)
                <section class="admin-panel p-5"><label class="block text-sm">Saved preparation notes<textarea name="before_you_begin" class="admin-input mt-2" rows="4" maxlength="20000">{{ old('before_you_begin', $case['before_you_begin'] ?? '') }}</textarea></label></section>
            @endif
            @if(filled($case['assistance_title'] ?? null) || filled($case['assistance_description'] ?? null) || (filled($case['action_label'] ?? null) && $case['action_label'] !== 'View guide'))
                <section class="admin-panel space-y-4 p-5" aria-label="Existing resident card content">
                    <h2 class="text-sm font-semibold">Existing resident card content</h2>
                    @foreach(['assistance_title' => ['Saved card title', 255], 'assistance_description' => ['Saved card description', 2000], 'action_label' => ['Saved action text', 120]] as $field => [$label, $limit])
                        <label class="block text-sm">{{ $label }}<textarea name="{{ $field }}" maxlength="{{ $limit }}" rows="2" class="admin-input mt-2">{{ old($field, $case[$field] ?? '') }}</textarea></label>
                    @endforeach
                </section>
            @endif
            @include('admin.government_ids.partials.checklists', ['deferred' => true, 'embeddedChecklist' => true, 'editable' => true, 'wizardMode' => true, 'wizardScenarioSelector' => false, 'createLabel' => 'Save special case guide'])
            @include('admin.catalog.partials.guide-editor', ['embeddedGuide' => true])
            <section class="admin-panel space-y-4 p-5 sm:p-6">
                <h2 class="text-sm font-semibold">Applicable fees and offices</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                @foreach(['fee_ids' => ['Fees', $governmentId->fees], 'office_ids' => ['Offices', $governmentId->offices]] as $field => [$title, $rows])
                    <fieldset class="space-y-2"><legend class="mb-2 text-sm font-medium">{{ $title }}</legend>
                        @forelse($rows as $row)<label class="flex items-start gap-2 text-sm"><input type="checkbox" name="advanced_{{ $field }}[]" value="{{ $row->id }}" @checked(in_array($row->id, old('advanced_'.$field, session()->hasOldInput() ? [] : ($case[$field] ?? []))))><span>{{ $row->label ?? $row->name }} @if($field === 'office_ids' && $row->status !== 'active')<span class="text-slate-500">({{ $row->status }})</span>@endif</span></label>@empty<p class="text-sm text-slate-500">No saved entries.</p>@endforelse
                    </fieldset>
                @endforeach
                </div>
            </section>
            <section class="admin-panel space-y-4 p-5 sm:p-6" x-data="{ sources: @js($sourceRows) }" aria-label="Additional source details">
                <h2 class="text-sm font-semibold">Source details and additional references</h2>
                <template x-for="(source, index) in sources" :key="index"><div class="space-y-3 border-t border-slate-100 pt-4">
                    <label class="block text-sm">Source title<input class="admin-input mt-2" :name="`sources[${index}][title]`" x-model="source.title" maxlength="255" :required="index > 0"></label>
                    <template x-if="index > 0"><div class="space-y-3">
                        <label class="block text-sm">Official URL<input class="admin-input mt-2" type="url" :name="`sources[${index}][url]`" x-model="source.url" maxlength="2048" required></label>
                        <label class="block text-sm">Date verified<input class="admin-input mt-2" type="date" max="{{ now('Asia/Manila')->toDateString() }}" :name="`sources[${index}][checked_on]`" x-model="source.checked_on"></label>
                        <button type="button" class="text-sm text-red-700" @click="sources.splice(index,1)">Remove source</button>
                    </div></template>
                    <label class="block text-sm">What does it support?<input class="admin-input mt-2" :name="`sources[${index}][scope]`" x-model="source.scope" maxlength="255"></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="block text-sm">Publication date<input class="admin-input mt-2" type="date" :name="`sources[${index}][published_on]`" x-model="source.published_on"></label><label class="block text-sm">Page or section<input class="admin-input mt-2" :name="`sources[${index}][section]`" x-model="source.section" maxlength="255"></label></div>
                </div></template>
                <button type="button" class="text-sm font-medium text-blue-900" :disabled="sources.length >= 50" @click="sources.push({title:'',url:'',checked_on:'',scope:'',published_on:'',section:''})">+ Add another source</button>
            </section>
        </div>
        <footer class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4"><a class="admin-secondary" href="{{ $backUrl }}">Cancel</a><button class="admin-primary" type="submit">Save special case guide</button></footer>
    </form>
</div>
@endsection
