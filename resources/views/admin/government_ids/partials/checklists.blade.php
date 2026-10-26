@php
    $wizardMode = $wizardMode ?? false;
    $checklistConfig = [
        'createLabel' => $createLabel ?? 'Create ID',
        'deferred' => $deferred ?? false,
        'oldPayload' => ($deferred ?? false) && is_string(old('requirements_payload')) ? old('requirements_payload') : null,
        'editable' => $editable ?? false,
        'wizardMode' => $wizardMode,
        'allowWizardCreate' => $allowWizardCreate ?? false,
        'qualifications' => \App\Models\GovernmentIdRequirementWay::QUALIFICATIONS,
        'checklists' => $checklists,
        'options' => $requirementOptions,
        'url' => ($deferred ?? false) ? null : route(($recordRoutes ?? 'admin.government-ids').'.checklists.store', $governmentId),
        'csrf' => csrf_token(),
        'applications' => \App\Models\GovernmentIdRequirementSet::APPLICATION_TYPES,
        'applicants' => \App\Models\GovernmentIdRequirementSet::APPLICANT_TYPES,
        'conditions' => \App\Models\GovernmentIdRequirementGroup::CONDITIONS,
        'formats' => \App\Models\GovernmentIdRequirementItem::FORMATS,
        'countableFormats' => \App\Models\GovernmentIdRequirementItem::COUNTABLE_FORMATS,
    ];
@endphp

@if(($editable ?? false) || count($checklists))
<section id="checklist-requirements" class="admin-panel mt-6 p-6" x-data="governmentIdChecklists(@js($checklistConfig))" @if($wizardMode) x-init="$nextTick(() => { if (checklists.length) open(checklists[0]); else if (allowWizardCreate) openEmptyWizard(); })" @endif @draft-guide-scenario.window="acceptGuideScenario($event.detail)">
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
            @if(($editable ?? false) && !$wizardMode)
                <p class="mt-1 text-sm text-slate-500">Checklists for each application and applicant type.</p>
            @endif
        </div>
        @if(($editable ?? false) && !$wizardMode)
            <button type="button" class="admin-primary" @click="open(null, $event)" :disabled="deleting !== null || saving || !!draft" x-ref="addChecklistButton">+ Add checklist</button>
        @endif
    </div>
    @if(($editable ?? false) && !$wizardMode)
        <p x-cloak x-show="checklists.length === 0 && !draft" class="text-sm text-slate-500">No checklists yet. Add a checklist for an application and applicant type.</p>
    @endif
    @unless($wizardMode)
    <div x-show="!draft" class="space-y-4">
        <template x-for="checklist in checklists" :key="checklist.id || checklist.client_key">
            <article class="rounded-xl border border-slate-200 p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    @unless($wizardMode)
                    <div>
                        <h3 class="font-semibold text-slate-900" x-text="checklistLabel(checklist)"></h3>
                    </div>
                    @endunless
                    @if($editable ?? false)
                        <div class="flex gap-3">
                            <button type="button" class="admin-secondary" @click="selected = String(checklists.indexOf(checklist)); open(checklist, $event)" :disabled="deleting !== null">{{ $wizardMode ? 'Edit Requirements' : 'Edit checklist' }}</button>
                            @unless($wizardMode)
                            <button type="button" class="text-sm text-red-700" @click="deleteChecklist(checklist)" :disabled="deleting !== null" :aria-label="'Delete ' + checklist.label" x-text="deleting !== null && deleting === checklist.id ? 'Deleting…' : 'Delete'"></button>
                            @endunless
                        </div>
                    @endif
                </div>
                <div class="space-y-4">
                    <template x-for="(group, groupIndex) in checklist.groups" :key="group.id || groupIndex">
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

    @endunless

    @if($editable ?? false)
    @unless($wizardMode)
    <label x-cloak x-show="showScenarioSelector" class="mt-4 block text-sm font-medium">Choose an existing checklist to edit
        <select class="admin-input mt-2" :value="selected" @change="selectChecklist($event.target.value); $event.target.value = selected" :disabled="saving || deleting !== null">
            <option value="">Select a checklist</option>
            <template x-for="(checklist, index) in checklists" :key="checklist.id || checklist.client_key"><option :value="String(index)" x-text="checklistLabel(checklist)"></option></template>
        </select>
    </label>
    @endunless
    @if($wizardMode && ($wizardScenarioSelector ?? false) && count($checklists) > 1)
        <label class="mt-4 block text-sm font-medium">Application checklist
            <select class="admin-input mt-2" :value="selected" @change="selectChecklist($event.target.value); $event.target.value = selected" :disabled="saving || !!editing">
                <template x-for="(checklist, index) in checklists" :key="checklist.id"><option :value="String(index)" x-text="checklistLabel(checklist)"></option></template>
            </select>
        </label>
    @endif
    <div x-ref="errors" x-show="errors.length" tabindex="-1" role="alert" class="my-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
        <ul class="list-disc pl-4"><template x-for="(error, index) in errors" :key="index"><li x-text="error"></li></template></ul>
    </div>
    <template x-if="draft">
        <fieldset :disabled="saving" class="mt-5 min-w-0 space-y-5" aria-label="Inline requirements editor">
            @unless($wizardMode)
            <h3 class="font-semibold">Applicant and application</h3>
            <details :open="selected === ''" class="space-y-3">
                <summary x-ref="scenarioDetails" x-show="selected !== ''" class="cursor-pointer text-sm font-medium">Edit applicant and application details</summary>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium">Who is applying?
                    <select x-model="draft.applicant_type" class="admin-input mt-2" :disabled="!!editing">
                        <option value="">Select applicant</option>
                        @foreach(\App\Models\GovernmentIdRequirementSet::APPLICANT_TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-medium">What kind of application?
                    <select x-ref="application" x-model="draft.application_type" class="admin-input mt-2" :disabled="!!editing">
                        <option value="">Select application</option>
                        @foreach(\App\Models\GovernmentIdRequirementSet::APPLICATION_TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
            <label x-show="draft.application_type === 'custom'" class="block text-sm font-medium">Custom application name<input x-model="draft.application_type_custom" maxlength="255" class="admin-input mt-2"></label>
            <p x-show="draft.applicant_type === 'adult' || draft.applicant_type === 'minor'" class="text-sm text-slate-500" x-text="draft.applicant_type === 'adult' ? 'Adult: 18 and above.' : 'Minor: below 18.'"></p>
            <div x-show="draft.applicant_type === 'custom'" class="space-y-3">
                <label class="block text-sm font-medium">Applicant label (optional)<input x-model="draft.applicant_type_custom" maxlength="255" class="admin-input mt-2"></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-medium">Minimum age (optional)<input type="number" min="0" max="65535" step="1" x-model="draft.min_age" class="admin-input mt-2"></label>
                    <label class="text-sm font-medium">Maximum age (optional)<input type="number" min="0" max="65535" step="1" x-model="draft.max_age" class="admin-input mt-2"></label>
                </div>
            </div>
            </details>
            @endunless
            <div class="grid items-start gap-5 xl:grid-cols-2">
                <div class="min-w-0 space-y-4">
                    <h3 class="font-semibold">Requirement cards</h3>
                    <p class="text-sm text-slate-500">Each card is a separate requirement. Alternatives inside a card are joined by OR.</p>
                    @if($wizardMode)
                        <p x-show="!draft.groups.length && !editing" class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500">No requirements added yet.</p>
                    @endif
                    <template x-for="(group, groupIndex) in draft.groups" :key="group.key">
                        <article class="min-w-0 rounded-xl border border-slate-300 p-4">
                            <div x-show="editingIndex !== groupIndex || !editing">
                                <h4 class="break-words font-semibold" x-text="requirementName(group)"></h4>
                                <p class="mt-1 text-sm text-blue-800" x-text="group.condition_type === 'always' ? 'Required' : 'Only if: ' + conditionLabel(group)"></p>
                                <template x-for="(way, index) in group.ways" :key="way.key"><div class="mt-2 text-sm"><p x-text="(index ? 'OR — ' : '') + waySummary(way)"></p><p class="text-slate-600" x-text="qualification(way)"></p></div></template>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <button type="button" class="admin-secondary" @click="editRequirement(groupIndex)" :disabled="!!editing || saving" :aria-label="'Edit ' + requirementName(group)" :aria-expanded="editingIndex === groupIndex && !!editing">Edit</button>
                                    <button type="button" class="text-sm text-red-700" @click="removeRequirement(groupIndex)" :disabled="!!editing || saving" :aria-label="'Delete ' + requirementName(group)">Delete</button>
                                </div>
                            </div>
                            <template x-if="editing && editingIndex === groupIndex"><div>@include('admin.government_ids.partials.requirement-editor')</div></template>
                        </article>
                    </template>
                    <template x-if="editing && editingIndex === null"><article class="rounded-xl border border-blue-400 p-4">@include('admin.government_ids.partials.requirement-editor')</article></template>
                    <button type="button" x-ref="addRequirementButton" class="admin-secondary" @click="addRequirement()" :disabled="!!editing || draft.groups.length >= 100">+ Add requirement</button>
                </div>
                @include('admin.government_ids.partials.requirements-preview')
            </div>
            @unless($wizardMode)
            <div class="space-y-3 border-t border-slate-200 pt-4">
                <p class="text-sm text-slate-600" x-text="deferred ? 'Apply checklist keeps this draft in the form. ' + createLabel + ' saves all sections together.' : 'Save checklist saves these requirements now. The main form saves other details and the Application Guide separately.'"></p>
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="admin-secondary" @click="close()" :disabled="saving">Cancel checklist changes</button>
                    <button type="button" class="admin-primary" @click="save()" :disabled="saving || !!editing" x-text="saving ? 'Saving…' : (deferred ? 'Apply checklist' : 'Save checklist')"></button>
                </div>
            </div>
            @endunless
        </fieldset>
    </template>
    @endif
</section>
@endif
