<section id="requirements" class="admin-panel mt-8" x-data="{ type: @js(in_array(old('type'), ['document', 'government_id', 'custom'], true) ? old('type') : 'document') }">
    <div class="border-b border-slate-100 px-5 py-5 sm:px-7">
        <h2 class="font-semibold">{{ $isDocument ? 'Requirements to obtain this document' : 'Application requirements' }}</h2>
        <p class="mt-1 text-sm leading-6 text-slate-500">Save your changes above before adding or removing requirements. Each requirement is saved separately.</p>
    </div>
    <div class="space-y-6 p-5 sm:p-7">
        @if($errors->getBag('requirement')->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Requirement could not be saved.</p>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->getBag('requirement')->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div>
            <h3 class="mb-2 text-sm font-semibold">Requirement groups</h3>
            <p class="mb-4 text-sm text-slate-500">Every group must be satisfied. Inside each group, choose all, any one, or at least N. Keep applicant-specific conditions in the notes for manual review.</p>
            @include('admin.catalog.structures', ['kind'=>'groups'])
        </div>
        <div class="space-y-3">
            @forelse($record->requirements as $requirement)
                <div class="flex items-start justify-between gap-4 rounded-lg border border-slate-200 p-4">
                    <div class="min-w-0">
                        <p class="break-words text-sm font-semibold">{{ $requirement->display_name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $requirement->group ? $requirement->group->name.' · '.$requirement->group->rule_label : 'Ungrouped — needs review' }}{{ $requirement->is_dependency ? ' · Obtain first' : '' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $requirement->type === 'custom' ? 'Other requirement' : ucfirst(str_replace('_', ' ', $requirement->type)) }}</p>
                        @if($requirement->description)<p class="mt-2 whitespace-pre-line break-words text-sm text-slate-600">{{ $requirement->description }}</p>@endif
                        <details class="mt-3" @if(old('_form')==='requirement-'.$requirement->id) open @endif>
                            <summary class="cursor-pointer text-xs text-[#012877] underline">Edit requirement</summary>
                            <form method="POST" action="{{ route('admin.'.$resource.'.requirements.update',[$record,$requirement]) }}" class="mt-3 space-y-3" @submit="if (contentDirty && !confirm('Save this requirement and discard unsaved general changes?')) $event.preventDefault()">
                                @csrf @method('PUT')
                                <input type="hidden" name="_form" value="requirement-{{ $requirement->id }}">
                                @if($requirement->type==='custom')<x-admin.field name="name" :id="'requirement-'.$requirement->id.'-name'" label="Requirement name" :value="old('_form')==='requirement-'.$requirement->id && is_string(old('name')) ? old('name') : $requirement->name" required maxlength="255" />@endif
                                @include('admin.catalog.requirement-options',['option'=>$requirement])
                                <x-admin.field name="description" :id="'requirement-'.$requirement->id.'-notes'" label="Notes from source" type="textarea" :value="old('_form')==='requirement-'.$requirement->id && is_string(old('description')) ? old('description') : $requirement->description" maxlength="5000" />
                                <button class="admin-secondary" type="submit">Save requirement</button>
                            </form>
                        </details>
                    </div>
                    <form method="POST" action="{{ route('admin.'.$resource.'.requirements.destroy', [$record, $requirement]) }}"
                        @submit="if (!confirm(contentDirty ? 'You have unsaved general changes. Remove this requirement and discard those changes?' : 'Remove this requirement from this application?')) $event.preventDefault()">
                        @csrf @method('DELETE')
                        <button type="submit" aria-label="Remove {{ $requirement->display_name }}" class="text-xs font-medium text-red-700 underline">Remove</button>
                    </form>
                </div>
            @empty
                <p class="rounded-lg border border-dashed border-slate-300 p-5 text-sm text-slate-500">No requirements recorded. This does not mean the application has no requirements.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('admin.'.$resource.'.requirements.store', $record) }}" class="space-y-4 rounded-lg bg-slate-50 p-4 sm:p-5"
            @submit="if (contentDirty && !confirm('You have unsaved general changes. Add this requirement and discard those changes?')) $event.preventDefault()">
            @csrf
            <input type="hidden" name="_form" value="requirement-new">
            <h3 class="text-sm font-semibold">Add a requirement</h3>
            @include('admin.catalog.requirement-options',['option'=>null])
            <div>
                <label for="requirement-type" class="mb-2 block text-sm font-medium">Requirement type</label>
                <select name="type" id="requirement-type" x-model="type" class="admin-input">
                    <option value="document">Existing document</option>
                    <option value="government_id">Existing government ID</option>
                    <option value="custom">Other requirement</option>
                </select>
            </div>
            <div x-show="type === 'document'">
                <label for="required-document" class="mb-2 block text-sm font-medium">Document *</label>
                <select id="required-document" name="referenced_document_id" :disabled="type !== 'document'" :required="type === 'document'" class="admin-input">
                    <option value="">Select a document</option>
                    @foreach($availableDocuments as $document)<option value="{{ $document->id }}" @selected(old('referenced_document_id') == $document->id)>{{ $document->name }}</option>@endforeach
                </select>
                @if($availableDocuments->isEmpty())<p class="mt-2 text-xs text-slate-500">No other documents are available. Add the document to the Documents directory first.</p>@endif
            </div>
            <div x-cloak x-show="type === 'government_id'">
                <label for="required-id" class="mb-2 block text-sm font-medium">Government ID *</label>
                <select id="required-id" name="referenced_government_id_id" :disabled="type !== 'government_id'" :required="type === 'government_id'" class="admin-input">
                    <option value="">Select a government ID</option>
                    @foreach($availableGovernmentIds as $governmentId)<option value="{{ $governmentId->id }}" @selected(old('referenced_government_id_id') == $governmentId->id)>{{ $governmentId->name }}</option>@endforeach
                </select>
                @if($availableGovernmentIds->isEmpty())<p class="mt-2 text-xs text-slate-500">No other government IDs are available.</p>@endif
            </div>
            <div x-cloak x-show="type === 'custom'">
                <label for="requirement-name" class="mb-2 block text-sm font-medium">Requirement name *</label>
                <input id="requirement-name" name="name" value="{{ $errors->getBag('requirement')->any() && is_string(old('name')) ? old('name') : '' }}" :disabled="type !== 'custom'" :required="type === 'custom'" maxlength="255" class="admin-input">
            </div>
            <div>
                <label for="requirement-description" class="mb-2 block text-sm font-medium">Notes from source (optional)</label>
                <textarea id="requirement-description" name="description" rows="3" maxlength="5000" class="admin-input">{{ $errors->getBag('requirement')->any() && is_string(old('description')) ? old('description') : '' }}</textarea>
            </div>
            <button type="submit" class="admin-primary">Add requirement</button>
        </form>
        <p class="text-xs leading-5 text-slate-500">“Obtain first” links an ID or document to a prerequisite. Acceptable alternatives stay inside their group; they are not all mandatory. These rules do not yet generate a roadmap.</p>
    </div>
</section>
