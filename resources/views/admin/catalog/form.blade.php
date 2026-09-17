@extends('admin.layouts.app')
@section('title', ($record->exists ? 'Edit ' : 'Add ').$label.' | IDireksyon Admin Panel')
@section('page_title', $plural)
@section('content')
@php
    $editing = $record->exists;
    $hasContentErrors = $errors->getBag('default')->any();
    $value = function ($key) use ($hasContentErrors, $record) {
        $input = $hasContentErrors ? old($key, $record->$key) : $record->$key;
        return is_scalar($input) ? (string) $input : '';
    };
    $steps = $hasContentErrors ? old('application_steps', []) : ($record->application_steps ?? []);
    $steps = collect(is_array($steps) ? $steps : [])->map(fn ($step) => [
        'title' => is_array($step) && is_string($step['title'] ?? null) ? $step['title'] : '',
        'details' => is_array($step) && is_string($step['details'] ?? null) ? $step['details'] : '',
    ])->values()->all();
@endphp
<div class="mx-auto max-w-5xl" x-data="{ contentDirty: false, steps: @js(array_values($steps ?? [])) }">
    <a class="text-sm text-slate-500 hover:text-[#012877]" href="{{ $editing ? route('admin.'.$resource.'.show', $record) : route('admin.'.$resource.'.index') }}">&larr; {{ $editing ? 'Back to '.$record->name : $plural }}</a>
    <div class="mb-7 mt-6">
        <p class="admin-eyebrow mb-2">{{ $label }}</p>
        <h1 class="text-3xl font-bold tracking-tight">{{ $editing ? 'Edit information' : 'Add '.strtolower($label) }}</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">{{ $isDocument ? 'Build a request guide for this document or certificate.' : 'Build an application guide for this government ID.' }} Leave unresearched details empty. Fields marked * are required.</p>
    </div>
    @if($editing) @include('admin.catalog.readiness') @endif
    @if($hasContentErrors)
        <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Please check the highlighted information.</p>
            <ul class="mt-2 list-disc pl-5">@foreach($errors->getBag('default')->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ $editing ? route('admin.'.$resource.'.update', $record) : route('admin.'.$resource.'.store') }}" @input="contentDirty = true" @change="contentDirty = true" class="space-y-6">
        @csrf
        @if($editing) @method('PUT') @endif
        <section class="admin-panel">
            <div class="border-b border-slate-100 px-5 py-5 sm:px-7"><h2 class="font-semibold">Basic information</h2><p class="mt-1 text-sm text-slate-500">The name, issuing organization, and purpose of this {{ strtolower($label) }}.</p></div>
            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
                <x-admin.field name="name" :label="$isDocument ? 'Document name' : 'ID name'" :value="$value('name')" :required="true" maxlength="255" class="sm:col-span-2" />
                <div><label for="agency_id" class="mb-2 block text-sm font-medium">Issuing agency</label><select name="agency_id" id="agency_id" class="admin-input"><option value="">Unassigned / use existing name</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected($value('agency_id')==$agency->id)>{{ $agency->name }}{{ $agency->abbreviation ? ' ('.$agency->abbreviation.')' : '' }}{{ $agency->status==='inactive' ? ' — inactive' : '' }}</option>@endforeach</select></div>
                @if(!$record->agency_id)<x-admin.field name="agency" label="Agency name (if not yet listed)" :value="$value('agency')" maxlength="255" hint="Prefer selecting an agency above. Existing text is preserved until assigned." />@endif
                @foreach(['category_id'=>['Category',$categories], 'level_id'=>['Issuing level',$levels]] as $field=>$config)
                    <div><label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $config[0] }}</label><select name="{{ $field }}" id="{{ $field }}" class="admin-input"><option value="">Not yet classified</option>@foreach($config[1] as $option)<option value="{{ $option->id }}" @selected($value($field)==$option->id)>{{ $field==='category_id' ? $option->display_name : $option->name }}{{ $option->status==='inactive' ? ' — inactive' : '' }}</option>@endforeach</select></div>
                @endforeach
                <div><label for="record_type" class="mb-2 block text-sm font-medium">Record type</label><select name="record_type" id="record_type" class="admin-input">@foreach($recordTypes as $key=>$text)<option value="{{ $key }}" @selected(($value('record_type') ?: 'other')===$key)>{{ $text }}</option>@endforeach</select></div>
                <x-admin.field name="purpose" label="Purpose" type="textarea" :value="$value('purpose')" maxlength="5000" class="sm:col-span-2" hint="What is this entry used for?" />
                <x-admin.field name="description" label="Description" type="textarea" :value="$value('description')" maxlength="10000" class="sm:col-span-2" />
            </div>
        </section>
        <section class="admin-panel">
            <div class="border-b border-slate-100 px-5 py-5 sm:px-7"><h2 class="font-semibold">Application Details</h2><p class="mt-1 text-sm text-slate-500">Add steps in the order stated by your source.</p></div>
            <div class="space-y-4 p-5 sm:p-7">
                <x-admin.field name="cost_notes" :label="$isDocument ? 'Issuance / request fee notes' : 'Application fee notes'" type="textarea" :value="$value('cost_notes')" maxlength="5000" class="sm:col-span-2" hint="Include applicable conditions from the source. Leave empty if unknown; empty does not mean free." />
                <x-admin.field name="validity" :label="$isDocument ? 'Validity / acceptance notes (optional)' : 'Validity period (optional)'" :value="$value('validity')" maxlength="255" :hint="$isDocument ? 'Only add verified validity or acceptance conditions. A document may have no fixed expiry.' : 'Leave empty until confirmed by a source.'" />
                <h3 class="font-medium">{{ $isDocument ? 'Request process' : 'Application process' }}</h3>
                <p x-show="steps.length === 0" class="rounded-lg border border-dashed border-slate-300 p-5 text-sm text-slate-500">No steps added. You can complete this section after researching the process.</p>
                <template x-for="(step, index) in steps" :key="index">
                    <div class="rounded-lg border border-slate-200 p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-[#012877]" x-text="'Step ' + (index + 1)"></p>
                            <div class="flex gap-3 text-xs">
                                <button type="button" class="text-slate-600 underline disabled:opacity-30" :disabled="index === 0" @click="steps.splice(index - 1, 0, steps.splice(index, 1)[0]); contentDirty = true" :aria-label="'Move step ' + (index + 1) + ' up'">Up</button>
                                <button type="button" class="text-slate-600 underline disabled:opacity-30" :disabled="index === steps.length - 1" @click="steps.splice(index + 1, 0, steps.splice(index, 1)[0]); contentDirty = true" :aria-label="'Move step ' + (index + 1) + ' down'">Down</button>
                                <button type="button" class="text-red-700 underline" @click="steps.splice(index, 1); contentDirty = true" :aria-label="'Remove step ' + (index + 1)">Remove</button>
                            </div>
                        </div>
                        <label :for="'step-title-' + index" class="mb-2 block text-sm font-medium">Step title *</label>
                        <input :id="'step-title-' + index" :name="'application_steps[' + index + '][title]'" x-model="step.title" required maxlength="255" class="admin-input">
                        <label :for="'step-details-' + index" class="mb-2 mt-3 block text-sm font-medium">Instructions (optional)</label>
                        <textarea :id="'step-details-' + index" :name="'application_steps[' + index + '][details]'" x-model="step.details" rows="3" maxlength="5000" class="admin-input"></textarea>
                    </div>
                </template>
                <button type="button" @click="steps.push({ title: '', details: '' }); contentDirty = true" :disabled="steps.length >= 30" class="admin-secondary disabled:opacity-50">Add step</button>
            </div>
        </section>
        <section class="admin-panel">
            <div class="border-b border-slate-100 px-5 py-5 sm:px-7"><h2 class="font-semibold">Sources &amp; Research</h2><p class="mt-1 text-sm text-slate-500">Record researched information and where it came from.</p></div>
            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
                @if($editing)<p class="text-xs text-slate-500 sm:col-span-2">Last updated: {{ $record->last_updated?->format('M j, Y g:i a') ?? 'Not recorded' }}</p>@endif
                <div><label for="research_status" class="mb-2 block text-sm font-medium">Research Status</label><select name="research_status" id="research_status" class="admin-input">@foreach($researchStages as $key=>$text)<option value="{{ $key }}" @selected(($value('research_status') ?: 'draft')===$key)>{{ $text }}</option>@endforeach</select></div>
                <x-admin.field name="research_notes" label="Research notes / unresolved questions" :value="$value('research_notes')" type="textarea" maxlength="10000" class="sm:col-span-2" hint="Explain legacy issuance, conflicting sources, and information still needing research. CMS presence does not mean public availability." />
                <x-admin.field name="source_url" label="Official source URL" type="url" :value="$value('source_url')" maxlength="2048" hint="Link to the official page supporting this information." />
                <x-admin.field name="source_checked_at" label="Source checked on" type="date" :value="$hasContentErrors ? $value('source_checked_at') : $record->source_checked_at?->format('Y-m-d')" hint="Only enter a date after checking the source. Saving an edit does not update this date." />
                <div class="space-y-4 sm:col-span-2">
                    <input type="hidden" name="is_published" value="0">
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_published" value="1" @checked($value('is_published')==='1') class="mt-1 rounded border-slate-300 text-[#012877]"><span>Approve for the resident directory<small class="mt-1 block text-slate-500">Requires verified content and a checked official source. Availability remains a separate status.</small></span></label>
                    <input type="hidden" name="requirements_reviewed" value="0">
                    @if($editing)
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="requirements_reviewed" value="1" @checked($value('requirements_reviewed')==='1') class="mt-1 rounded border-slate-300 text-[#012877]"><span>I checked the complete requirement groups and obtain-first links against the source<small class="mt-1 block text-slate-500">Save groups and requirements below first. Changing them clears this confirmation. New issuance must be available; conditional rules still need manual review.</small></span></label>
                    @endif
                </div>
            </div>
        </section>
        <section class="admin-panel p-5 sm:p-7">
            <h2 class="font-semibold">Offices &amp; Availability</h2>
                <div><label for="availability_status" class="mb-2 mt-5 block text-sm font-medium">Issuance availability</label><select name="availability_status" id="availability_status" class="admin-input">@foreach($availabilityStates as $key=>$text)<option value="{{ $key }}" @selected(($value('availability_status') ?: 'unknown')===$key)>{{ $text }}</option>@endforeach</select></div>

            <p class="mb-4 mt-1 text-sm text-slate-500">Link only offices confirmed to offer this service. Requirements and schedules may differ by office.</p>
            <input type="hidden" name="office_ids[]" value="">
            @php($selectedOffices = $hasContentErrors ? old('office_ids',[]) : ($editing ? $record->offices->pluck('id')->all() : []))
            <div class="grid max-h-64 gap-3 overflow-y-auto sm:grid-cols-2">
                @forelse($offices as $office)<label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm"><input type="checkbox" name="office_ids[]" value="{{ $office->id }}" @checked(in_array($office->id,is_array($selectedOffices)?$selectedOffices:[])) class="mt-1 rounded border-slate-300 text-[#012877]"><span>{{ $office->name }}<small class="block text-slate-500">{{ $office->barangay?->name ?? $office->municipality }} · {{ ucfirst(str_replace('_',' ',$office->status)) }}</small></span></label>@empty<p class="text-sm text-slate-500">No offices recorded yet.</p>@endforelse
            </div>
            @can('manage-reference-data')
            <a href="{{ route('admin.references.index','offices') }}" target="_blank" rel="noopener" class="mt-4 inline-block text-sm text-[#012877] underline">Manage office directory (new tab)</a>
            @endcan
        </section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a class="admin-secondary" href="{{ $editing ? route('admin.'.$resource.'.show', $record) : route('admin.'.$resource.'.index') }}">Cancel</a>
            <button type="submit" class="admin-primary">{{ $editing ? 'Save changes' : 'Create '.$label }}</button>
        </div>
    </form>
    @if($editing)
        @include('admin.catalog.requirements')
        <section id="fees" class="admin-panel mt-8 p-5 sm:p-7">
            <h2 class="font-semibold">Fee breakdown</h2>
            <p class="mb-5 mt-1 text-sm text-slate-500">Save general changes above first. Add researched charges individually; optional and alternative fees are kept separate. Existing fee notes stay unchanged.</p>
            @include('admin.catalog.structures', ['kind'=>'fees'])
        </section>
    @else
        <p class="mt-6 rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-500">Create this entry first, then add its requirements from Edit information.</p>
    @endif
</div>
@endsection
