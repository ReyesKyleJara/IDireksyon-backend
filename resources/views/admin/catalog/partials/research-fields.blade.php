@php
    $research = old('research_metadata', $governmentId?->research_metadata ?? ['status' => 'draft']);
@endphp
<section class="admin-panel space-y-5 p-6" x-data="{ sources: @js($research['sources'] ?? []) }" aria-label="Sources and research review">
    <h3 class="text-lg font-semibold">Sources and research review</h3>
    <p class="text-sm text-slate-600">Saving does not verify government instructions. Record what each official source supports and the date you actually checked it.</p>
    <label class="block text-sm font-medium">Research status
        <select name="research_metadata[status]" class="admin-input mt-2">
            @foreach(['draft' => 'Draft — encoding in progress', 'needs_research' => 'Needs content research', 'reviewed' => 'Reviewed against official sources'] as $code => $label)
                <option value="{{ $code }}" @selected(($research['status'] ?? 'draft') === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-medium">Validity / recency explanation
        <textarea name="research_metadata[validity_note]" maxlength="2000" class="admin-input mt-2" placeholder="e.g. The record does not expire; the receiving agency may require a recently issued copy.">{{ $research['validity_note'] ?? '' }}</textarea>
    </label>
    <template x-for="(source, index) in sources" :key="index">
        <fieldset class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2">
            <legend class="text-sm font-medium" x-text="'Official source ' + (index + 1)"></legend>
            <label class="text-sm">Title / issuing agency<input class="admin-input" :name="`research_metadata[sources][${index}][title]`" x-model="source.title" maxlength="255" required></label>
            <label class="text-sm">Official URL<input type="url" class="admin-input" :name="`research_metadata[sources][${index}][url]`" x-model="source.url" maxlength="2048" required></label>
            <label class="text-sm">Checked on<input type="date" class="admin-input" :name="`research_metadata[sources][${index}][checked_on]`" x-model="source.checked_on" max="{{ now('Asia/Manila')->toDateString() }}"></label>
            <label class="text-sm">What this source supports / channel<input class="admin-input" :name="`research_metadata[sources][${index}][scope]`" x-model="source.scope" maxlength="255"></label>
            <label class="text-sm">Publication / effective date<input type="date" class="admin-input" :name="`research_metadata[sources][${index}][published_on]`" x-model="source.published_on"></label>
            <label class="text-sm">Page / section<input class="admin-input" :name="`research_metadata[sources][${index}][section]`" x-model="source.section" maxlength="255"></label>
            <button type="button" class="text-left text-sm text-red-700" @click="sources.splice(index, 1)">Remove source from this draft</button>
        </fieldset>
    </template>
    <button type="button" class="admin-secondary" :disabled="sources.length >= 50" @click="sources.push({title:'',url:'',checked_on:'',scope:'',published_on:'',section:''})">+ Add official source</button>
</section>
