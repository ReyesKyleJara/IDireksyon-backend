@php
    $prerequisiteRows = old('prerequisites', $governmentId->prerequisites->map(fn ($row) => $row->only(['id', 'type', 'government_id_id', 'document_id', 'custom_name', 'condition']))->all());
    if (old('prerequisites_present') && !old('prerequisites')) $prerequisiteRows = [];
    $prerequisiteRows = is_array($prerequisiteRows) ? array_values(array_filter($prerequisiteRows, 'is_array')) : [];
@endphp
<section class="admin-panel space-y-4 p-6" aria-labelledby="prerequisites-heading" x-data="catalogPrerequisites(@js($prerequisiteRows))">
    <h2 id="prerequisites-heading" class="text-lg font-semibold text-slate-900">Prerequisites / Dependencies</h2>
    <p class="text-sm text-slate-600">Add documents, IDs, or preparatory actions that must be obtained or completed before applying.</p>
    <p class="text-xs text-slate-500">Continue saves prerequisites. Application Requirements are edited separately below.</p>
    <input type="hidden" name="prerequisites_present" value="1" disabled x-bind:disabled="false">
    @foreach($errors->getMessages() as $field => $messages)
        @if(str_starts_with($field, 'prerequisites'))
            @foreach($messages as $message)<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@endforeach
        @endif
    @endforeach
    <noscript><p>Enable JavaScript to edit prerequisites. Existing prerequisites will be preserved.</p></noscript>
    <template x-for="(row, index) in rows" :key="row.key">
        <fieldset class="space-y-3 rounded-lg border border-slate-300 p-4">
            <legend class="px-1 font-medium" x-text="'Prerequisite ' + (index + 1)"></legend>
            <input type="hidden" :name="'prerequisites[' + index + '][id]'" :value="row.id || ''">
            <label class="block text-sm">Source type
                <select class="admin-input mt-1" :name="'prerequisites[' + index + '][type]'" x-model="row.type" @change="changeType(row)">
                    <option value="government_id">Government ID</option>
                    <option value="document">Document</option>
                    <option value="custom">Custom Prerequisite</option>
                </select>
            </label>
            @foreach(['government_id' => 'Government ID', 'document' => 'Document'] as $sourceType => $sourceLabel)
            <template x-if="row.type === @js($sourceType)">
                <label class="block text-sm">Linked {{ $sourceLabel }}
                    <select class="admin-input mt-1" :name="'prerequisites[' + index + '][{{ $sourceType }}_id]'" x-model="row.{{ $sourceType }}_id" required>
                        <option value="">Select catalog record</option>
                        @foreach($requirementOptions as $option)
                            @if($option['type'] === $sourceType && !($sourceType === ($recordKind === 'document' ? 'document' : 'government_id') && $option['id'] === $governmentId->id))
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>
            </template>
            @endforeach
            <template x-if="row.type === 'custom'">
                <label class="block text-sm">Custom prerequisite
                    <input class="admin-input mt-1" :name="'prerequisites[' + index + '][custom_name]'" x-model="row.custom_name" maxlength="255" required>
                </label>
            </template>
            <label class="block text-sm">Applies When / Condition
                <textarea class="admin-input mt-1" :name="'prerequisites[' + index + '][condition]'" x-model="row.condition" rows="2" maxlength="1000" placeholder="Optional condition"></textarea>
            </label>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="admin-secondary" @click="move(index, -1)" :disabled="index === 0" :aria-label="'Move prerequisite ' + (index + 1) + ' up'">Move up</button>
                <button type="button" class="admin-secondary" @click="move(index, 1)" :disabled="index === rows.length - 1" :aria-label="'Move prerequisite ' + (index + 1) + ' down'">Move down</button>
                <button type="button" class="admin-secondary text-red-700" @click="remove(index)" :aria-label="'Remove prerequisite ' + (index + 1)">Remove</button>
            </div>
        </fieldset>
    </template>
    <button type="button" class="admin-secondary" @click="add()" :disabled="rows.length >= 100">+ Add Prerequisite</button>
</section>
