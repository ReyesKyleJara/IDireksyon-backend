@php
    $checklistConfig = [
        'deferred' => $deferred ?? false,
        'oldPayload' => ($deferred ?? false) && is_string(old('requirements_payload')) ? old('requirements_payload') : null,
        'editable' => $editable ?? false,
        'qualifications' => \App\Models\GovernmentIdRequirementWay::QUALIFICATIONS,
        'checklists' => $checklists,
        'options' => $requirementOptions,
        'url' => ($deferred ?? false) ? null : route('admin.government-ids.checklists.store', $governmentId),
        'csrf' => csrf_token(),
        'applications' => \App\Models\GovernmentIdRequirementSet::APPLICATION_TYPES,
        'applicants' => \App\Models\GovernmentIdRequirementSet::APPLICANT_TYPES,
        'conditions' => \App\Models\GovernmentIdRequirementGroup::CONDITIONS,
        'formats' => \App\Models\GovernmentIdRequirementItem::FORMATS,
        'countableFormats' => \App\Models\GovernmentIdRequirementItem::COUNTABLE_FORMATS,
    ];
@endphp

@if(($editable ?? false) || count($checklists))
<section id="checklist-requirements" class="admin-panel mt-6 p-6" x-data="governmentIdChecklists(@js($checklistConfig))" @draft-guide-scenario.window="acceptGuideScenario($event.detail)">
    @if($deferred ?? false)
        <input type="hidden" name="requirements_payload" x-ref="pendingPayload" :value="creationPayload" :disabled="!ready" disabled>
        <p x-show="formError" x-text="formError" role="alert" class="mb-3 text-sm text-red-700"></p>
        <button type="button" x-show="restoreError" @click="discardUnreadableDrafts()" class="admin-secondary mb-3">Discard unreadable requirements</button>
        @foreach($errors->getMessages() as $field => $messages)
            @if(str_starts_with($field, 'requirements_payload'))
                @foreach($messages as $message)<p class="mb-2 text-sm text-red-700" role="alert">{{ $message }}</p>@endforeach
            @endif
        @endforeach
        <noscript><p class="mb-3 text-sm text-slate-600">Enable JavaScript to add requirement checklists.</p></noscript>
    @endif
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Requirements</h2>
            @if($editable ?? false)
                <p class="mt-1 text-sm text-slate-500">Checklists for each application and applicant type.</p>
            @endif
        </div>
        @if($editable ?? false)
            <button type="button" class="admin-primary" @click="open(null, $event)" :disabled="deleting !== null">+ Add checklist</button>
        @endif
    </div>
    @if($editable ?? false)
        <p x-show="checklists.length === 0" class="text-sm text-slate-500">Add a checklist for an application and applicant type.</p>
    @endif
    <div class="space-y-4">
        <template x-for="checklist in checklists" :key="checklist.id || checklist.client_key">
            <article class="rounded-xl border border-slate-200 p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold text-slate-900" x-text="checklist.label"></h3>
                    @if($editable ?? false)
                        <div class="flex gap-3">
                            <button type="button" class="admin-secondary" @click="open(checklist, $event)" :disabled="deleting !== null">Edit checklist</button>
                            <button type="button" class="text-sm text-red-700" @click="deleteChecklist(checklist)" :disabled="deleting !== null" :aria-label="'Delete ' + checklist.label" x-text="deleting !== null && deleting === checklist.id ? 'Deleting…' : 'Delete'"></button>
                        </div>
                    @endif
                </div>
                <div class="space-y-4">
                    <template x-for="group in checklist.groups" :key="group.id || group.key">
                        <div>
                            <p class="text-sm font-semibold text-slate-800" x-text="requirementName(group)"></p>
                            <p x-show="group.condition_type !== 'always'" class="mt-1 text-xs text-blue-800" x-text="'Only if: ' + conditionLabel(group)"></p>
                            <template x-for="(way, wayIndex) in ways(group)" :key="wayIndex">
                                <div class="mt-2 text-sm text-slate-600">
                                    <p x-show="wayIndex > 0" class="my-2 font-semibold">OR</p>
                                    <p x-show="way.items.length > 1 || group.title || ways(group).length > 1" x-text="waySummary(way)"></p>
                                    <p x-show="qualification(way)" x-text="qualification(way)" class="mt-1 text-xs text-blue-800"></p>
                                    <ul class="mt-1 space-y-1">
                                        <template x-for="(item, itemIndex) in way.items" :key="item.id || itemIndex">
                                            <li>
                                                <span x-show="way.items.length > 1" x-text="itemName(item)"></span>
                                                <span x-show="item.quantity" x-text="'Quantity: ' + item.quantity"></span>
                                                <span x-show="submission(item)" x-text="submission(item)"></span>
                                                <p x-show="item.instructions" x-text="item.instructions" class="whitespace-pre-line text-xs leading-5 text-slate-500"></p>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </article>
        </template>
    </div>

    @if($editable ?? false)
    <style>#checklist-dialog::backdrop { background: rgb(15 23 42 / .5); }</style>
    {{-- Keep the modal form outside the surrounding ID form. Alpine retains its component scope. --}}
    <template x-teleport="body">
    <dialog id="checklist-dialog" x-ref="dialog" aria-labelledby="checklist-dialog-title"
        @cancel.prevent="close()" class="m-auto rounded-2xl border-0 bg-white p-0 shadow-xl"
        style="width: min(720px, calc(100vw - 2rem)); max-width: none; max-height: calc(100dvh - 2rem);">
        <form @submit.prevent="editing ? finishRequirement() : save()" novalidate class="flex flex-col" style="max-height: calc(100dvh - 2rem);">
            <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-6 py-4">
                <div>
                    <button type="button" x-show="editing && !editing.choosing" @click="chooseAgain()" class="mb-2 text-sm font-medium text-[#012877]">← Back to choices</button>
                    <h2 id="checklist-dialog-title" class="text-lg font-bold text-slate-900" x-text="requirementEditorTitle()"></h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $governmentId->name ?? 'New ID or Credential' }}</p>
                </div>
                <button type="button" aria-label="Close checklist" class="text-2xl text-slate-500" @click="close()" :disabled="saving">×</button>
            </header>
            <div x-ref="dialogBody" class="min-h-0 overflow-y-auto px-6 py-5">
                <div x-ref="errors" x-show="errors.length" tabindex="-1" role="alert" class="mb-5 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc pl-4"><template x-for="(error, index) in errors" :key="index"><li x-text="error"></li></template></ul>
                </div>
                <template x-if="draft">
                    <fieldset :disabled="saving" class="min-w-0 space-y-5">
                        <div x-show="!editing" class="space-y-5">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="text-sm font-medium">Application type
                                    <select x-ref="application" x-model="draft.application_type" class="admin-input mt-2">
                                        <option value="">Select application</option>
                                        @foreach(\App\Models\GovernmentIdRequirementSet::APPLICATION_TYPES as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-sm font-medium">Applicant type
                                    <select x-model="draft.applicant_type" class="admin-input mt-2">
                                        <option value="">Select applicant</option>
                                        @foreach(\App\Models\GovernmentIdRequirementSet::APPLICANT_TYPES as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <label x-show="draft.application_type === 'custom'" class="block text-sm font-medium">Custom application name
                                <input x-model="draft.application_type_custom" maxlength="255" class="admin-input mt-2">
                            </label>
                            <p x-show="draft.applicant_type === 'adult' || draft.applicant_type === 'minor'" class="text-xs text-slate-500" x-text="draft.applicant_type === 'adult' ? 'Adult: 18 and above.' : 'Minor: below 18.'"></p>
                            <div x-show="draft.applicant_type === 'custom'" class="space-y-3">
                                <label class="block text-sm font-medium">Applicant label (optional)<input x-model="draft.applicant_type_custom" maxlength="255" class="admin-input mt-2"></label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="text-sm font-medium">Minimum age<input type="number" min="0" max="65535" step="1" x-model="draft.min_age" class="admin-input mt-2"></label>
                                    <label class="text-sm font-medium">Maximum age<input type="number" min="0" max="65535" step="1" x-model="draft.max_age" class="admin-input mt-2"></label>
                                </div>
                                <p class="text-xs text-slate-500">Leave an age blank if that limit does not apply.</p>
                            </div>

                            <div class="space-y-3">
                                <h3 class="text-sm font-semibold text-slate-900">What does this applicant need?</h3>
                                <template x-for="(group, groupIndex) in draft.groups" :key="group.key">
                                    <article x-show="editingIndex !== groupIndex || !editing" class="rounded-xl border border-slate-200 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-800" x-text="requirementName(group)"></p>
                                                <p class="mt-1 text-xs text-slate-500" x-text="group.condition_type === 'always' ? 'Required' : 'Only if: ' + conditionLabel(group)"></p>
                                                <template x-for="(way, wayIndex) in group.ways" :key="way.key">
                                                    <div class="mt-1 text-xs text-slate-500">
                                                        <p x-show="group.ways.length > 1 || way.items.length !== 1 || itemName(way.items[0]) !== requirementName(group)" x-text="(wayIndex > 0 ? 'OR — ' : '') + waySummary(way)"></p>
                                                        <p x-show="qualification(way)" x-text="qualification(way)"></p>
                                                        <template x-for="item in way.items" :key="item.key">
                                                            <span class="block">
                                                                <span x-show="item.quantity" x-text="'Quantity: ' + item.quantity"></span>
                                                                <span x-show="submission(item)" x-text="submission(item)"></span>
                                                            </span>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                            <div class="flex shrink-0 gap-3 text-sm">
                                                <button type="button" @click="editRequirement(groupIndex)" :disabled="!!editing" class="text-[#012877] disabled:opacity-40">Edit</button>
                                                <button type="button" @click="removeRequirement(groupIndex)" :disabled="!!editing" class="text-red-700 disabled:opacity-40">Remove</button>
                                            </div>
                                        </div>
                                    </article>
                                </template>
                            </div>
                            <button type="button" x-ref="addRequirementButton" class="admin-secondary" @click="addRequirement()" :disabled="draft.groups.length >= 100">+ Add requirement</button>
                        </div>
                        <template x-if="editing">
                            @include('admin.government_ids.partials.requirement-editor')
                        </template>

                    </fieldset>
                </template>
            </div>
            <footer x-show="!editing" class="shrink-0 border-t border-slate-200 bg-white px-6 py-4">
                <p class="mb-3 text-xs text-slate-500" x-text="deferred ? 'Apply this checklist to the form. Create ID saves it with all the other sections.' : 'Save checklist to keep your requirement changes. Other ID details are saved separately.'"></p>
                <div class="flex justify-end gap-3">
                    <button type="button" class="admin-secondary" @click="close()" :disabled="saving">Cancel</button>
                    <button type="submit" class="admin-primary" :disabled="saving" x-text="saving ? 'Saving…' : (deferred ? 'Apply checklist' : 'Save checklist')"></button>
                </div>
            </footer>
        </form>
    </dialog>
    </template>
    @endif
</section>
@endif
