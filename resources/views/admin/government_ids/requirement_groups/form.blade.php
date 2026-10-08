@extends('admin.layouts.app')
@section('title', ($group->exists ? 'Edit Requirement Group' : 'Add Requirement Group').' | IDireksyon')
@section('page_title', $group->exists ? 'Edit Requirement Group' : 'Add Requirement Group')
@section('content')
@php
    $value = function ($field) use ($group) {
        $input = old($field, $group->getAttribute($field));
        return is_scalar($input) ? (string) $input : '';
    };
    $saved = $group->exists ? $group->items->map(fn ($item) => $item->only(['id', ...\App\Models\GovernmentIdRequirementItem::EDITABLE_FIELDS]))->all() : [];
    $rows = session()->hasOldInput('items_present') ? old('items', []) : $saved;
    $rows = is_array($rows) ? array_values($rows) : [];
    $rows = collect($rows)->filter(fn ($row) => is_array($row))->values()->map(function ($row, $index) {
        $safe = ['key' => $index];
        foreach (['id', ...\App\Models\GovernmentIdRequirementItem::EDITABLE_FIELDS] as $field) {
            $safe[$field] = is_scalar($row[$field] ?? null) ? (string) $row[$field] : '';
        }
        return $safe;
    })->values()->all();
    $idOptions = $governmentIds->map(fn ($id) => ['id' => (string) $id->id, 'name' => $id->name])->values();
    $documentOptions = $documents->map(fn ($document) => ['id' => (string) $document->id, 'name' => $document->name])->values();
@endphp
<div class="mx-auto max-w-3xl">
    <a href="{{ route('admin.government-ids.requirement-sets.groups.index', [$governmentId, $set]) }}" class="text-sm text-slate-500">← Back to requirements</a>
    <div class="mb-7 mt-5">
        <p class="admin-eyebrow mb-2">{{ $governmentId->name }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $group->exists ? 'Edit Requirement Group' : 'Add Requirement Group' }}</h1>
        <p class="mt-2 text-sm text-slate-500">{{ $set->display_label }}</p>
    </div>
    @if($errors->any())
        <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="mb-2 font-semibold">Please check the following:</p>
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ $group->exists ? route('admin.government-ids.requirement-sets.groups.update', [$governmentId, $set, $group]) : route('admin.government-ids.requirement-sets.groups.store', [$governmentId, $set]) }}"
        class="space-y-6"
        x-data="{
            rule: @js($value('rule') ?: 'all'),
            condition: @js($value('condition_type') ?: 'always'),
            rows: @js($rows),
            nextKey: {{ count($rows) }},
            idOptions: @js($idOptions),
            documentOptions: @js($documentOptions),
            countableFormats: @js(\App\Models\GovernmentIdRequirementItem::COUNTABLE_FORMATS),
            addItem() {
                if (this.rows.length >= 50) return;
                this.rows.push({key: this.nextKey++, id: '', type: 'document', government_id_id: '', document_id: '', custom_name: '', submission_format: 'not_specified', submission_format_custom: '', copies: '', instructions: ''});
            },
            move(index, offset) {
                const target = index + offset;
                if (target &lt; 0 || target >= this.rows.length) return;
                const row = this.rows.splice(index, 1)[0];
                this.rows.splice(target, 0, row);
            },
            changeType(row) {
                row.government_id_id = ''; row.document_id = ''; row.custom_name = '';
            }
        }">
        @csrf
        @if($group->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="items_present" value="1">
        <section class="admin-panel space-y-5 p-6">
            <div>
                <label for="title" class="mb-2 block text-sm font-medium">Group Heading <span class="font-normal text-slate-500">(optional)</span></label>
                <input id="title" name="title" value="{{ $value('title') }}" maxlength="255" class="admin-input" placeholder="e.g. Accepted proof of identity">
            </div>
            <div>
                <label for="rule" class="mb-2 block text-sm font-medium">How should these items be satisfied?</label>
                <select id="rule" name="rule" x-model="rule" class="admin-input" required>
                    @foreach($rules as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-sm text-slate-500" x-text="rule === 'choose_one' ? 'The applicant needs any one option. Add at least two options.' : 'The applicant needs every item in this group.'"></p>
            </div>
            <div>
                <label for="condition_type" class="mb-2 block text-sm font-medium">When does this group apply?</label>
                <select id="condition_type" name="condition_type" x-model="condition" class="admin-input" required>
                    @foreach($conditions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="condition === 'custom'" x-cloak>
                <label for="condition_custom" class="mb-2 block text-sm font-medium">Custom Condition Question *</label>
                <textarea id="condition_custom" name="condition_custom" rows="3" maxlength="1000" class="admin-input" :disabled="condition !== 'custom'" :required="condition === 'custom'" placeholder="e.g. Is a legal guardian accompanying the applicant?">{{ $value('condition_custom') }}</textarea>
                <p class="mt-2 text-sm text-slate-500">Use a clear question that can be answered Yes, No, or Not sure. Yes means this group applies.</p>
            </div>
        </section>
        <section class="admin-panel p-6">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-slate-900">Requirement Items</h2>
                <p class="mt-1 text-sm text-slate-500">Select existing IDs and Documents whenever available. Use Custom only for items that do not belong in either directory.</p>
                <div class="mt-2 flex flex-wrap gap-4 text-sm">
                    <a href="{{ route('admin.documents.create') }}" target="_blank" rel="noopener noreferrer" class="text-[#012877] hover:underline">Add Document in new tab ↗</a>
                    <a href="{{ route('admin.government-ids.create') }}" target="_blank" rel="noopener noreferrer" class="text-[#012877] hover:underline">Add Government ID in new tab ↗</a>
                </div>
                <p class="mt-2 text-xs text-slate-500">New directory records appear after reloading this form. Save existing work before reloading.</p>
            </div>
            <p x-show="rows.length === 0" class="mb-4 text-sm text-slate-500">Add an item to start this group.</p>
            <div class="space-y-5">
                <template x-for="(row, index) in rows" :key="row.key">
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <input type="hidden" :name="'items[' + index + '][id]'" :value="row.id">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h3 class="font-semibold text-slate-900" x-text="'Item ' + (index + 1)"></h3>
                            <div class="flex gap-3 text-sm">
                                <button type="button" @click="move(index, -1)" :disabled="index === 0" :aria-label="'Move item ' + (index + 1) + ' up'" class="text-slate-600 disabled:opacity-40">↑ Up</button>
                                <button type="button" @click="move(index, 1)" :disabled="index === rows.length - 1" :aria-label="'Move item ' + (index + 1) + ' down'" class="text-slate-600 disabled:opacity-40">↓ Down</button>
                                <button type="button" @click="rows.splice(index, 1)" :aria-label="'Remove item ' + (index + 1)" class="font-medium text-red-700">Remove</button>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <label class="block text-sm font-medium">Requirement Type *
                                <select :name="'items[' + index + '][type]'" x-model="row.type" @change="changeType(row)" class="admin-input mt-2" required>
                                    @foreach($types as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label x-show="row.type === 'government_id'" class="block text-sm font-medium">Government ID *
                                <select :name="'items[' + index + '][government_id_id]'" x-model="row.government_id_id" :disabled="row.type !== 'government_id'" :required="row.type === 'government_id'" class="admin-input mt-2">
                                    <option value="">Select a Government ID</option>
                                    <template x-for="option in idOptions" :key="option.id"><option :value="option.id" :selected="row.government_id_id === option.id" x-text="option.name"></option></template>
                                </select>
                            </label>
                            <label x-show="row.type === 'document'" class="block text-sm font-medium">Document *
                                <select :name="'items[' + index + '][document_id]'" x-model="row.document_id" :disabled="row.type !== 'document'" :required="row.type === 'document'" class="admin-input mt-2">
                                    <option value="">Select a Document</option>
                                    <template x-for="option in documentOptions" :key="option.id"><option :value="option.id" :selected="row.document_id === option.id" x-text="option.name"></option></template>
                                </select>
                            </label>
                            <label x-show="row.type === 'custom'" class="block text-sm font-medium">Custom Requirement Name *
                                <input :name="'items[' + index + '][custom_name]'" x-model="row.custom_name" :disabled="row.type !== 'custom'" :required="row.type === 'custom'" maxlength="255" class="admin-input mt-2" placeholder="e.g. Passport-size photo">
                            </label>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block text-sm font-medium">Submission Format
                                    <select :name="'items[' + index + '][submission_format]'" x-model="row.submission_format" class="admin-input mt-2" required>
                                        @foreach($formats as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label x-show="countableFormats.includes(row.submission_format)" class="block text-sm font-medium">
                                    <span x-text="row.submission_format === 'original_photocopy' ? 'Number of Photocopies' : 'Number of Copies'"></span>
                                    <input type="number" :name="'items[' + index + '][copies]'" x-model="row.copies" :disabled="!countableFormats.includes(row.submission_format)" min="1" max="65535" step="1" class="admin-input mt-2" placeholder="Optional">
                                </label>
                            </div>
                            <label x-show="row.submission_format === 'custom'" class="block text-sm font-medium">Custom Submission Format *
                                <input :name="'items[' + index + '][submission_format_custom]'" x-model="row.submission_format_custom" :disabled="row.submission_format !== 'custom'" :required="row.submission_format === 'custom'" maxlength="255" class="admin-input mt-2">
                            </label>
                            <label class="block text-sm font-medium">Additional Instructions <span class="font-normal text-slate-500">(optional)</span>
                                <textarea :name="'items[' + index + '][instructions]'" x-model="row.instructions" rows="2" maxlength="10000" class="admin-input mt-2" placeholder="e.g. Front and back with three specimen signatures."></textarea>
                            </label>
                        </div>
                    </article>
                </template>
            </div>
            <button type="button" @click="addItem()" :disabled="rows.length >= 50" class="admin-secondary mt-5">+ Add Item</button>
        </section>
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.government-ids.requirement-sets.groups.index', [$governmentId, $set]) }}" class="admin-secondary">Cancel</a>
            <button type="submit" class="admin-primary">Save Group</button>
        </div>
    </form>
</div>
@endsection
