@php
    $guideConfig = [
        'deferred' => $deferred ?? false,
        'scenarios' => app(\App\Services\GovernmentIdApplicationGuide::class)->present($governmentId ?? null),
        'oldPayload' => is_string(old('application_guide_payload')) ? old('application_guide_payload') : null,
    ];
@endphp
<section class="admin-panel p-6" aria-label="Application Guide" x-data="governmentIdGuide(@js($guideConfig))" @guide-scenario-changed.window="scenarioChanged($event.detail)">
    <input type="hidden" name="application_guide_payload" :disabled="!ready" :value="payload" disabled>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="text-lg font-semibold text-slate-900">Application Guide</h2><p class="mt-1 text-sm text-slate-500">Explain what the applicant should do, one step at a time.</p></div>
        <span class="text-xs text-slate-500">Saved with the main form</span>
    </div>
    <noscript><p class="mt-3 text-sm text-amber-700">Enable JavaScript to edit the Application Guide. Existing steps will be preserved.</p></noscript>
    @foreach($errors->getMessages() as $field => $messages)
        @if(str_starts_with($field, 'application_guide'))
            @foreach($messages as $message)<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@endforeach
        @endif
    @endforeach
    <p x-show="error && !modal" x-text="error" class="mt-3 text-sm text-red-600" role="alert"></p>
    <div class="mt-5 flex flex-wrap items-end gap-3">
        <label class="min-w-0 flex-1 text-sm font-medium">Applicant / application
            <select x-model="selected" class="admin-input mt-2" :disabled="!scenarios.length">
                <option value="" x-show="!scenarios.length">Add an applicant/application scenario</option>
                <template x-for="scenario in scenarios" :key="scenario.key"><option :value="scenario.key" x-text="label(scenario)"></option></template>
            </select>
        </label>
        <button type="button" class="admin-secondary" @click="startScenario()" :disabled="addingScenario">+ Add scenario</button>
    </div>
    <p class="mt-2 text-xs text-slate-500">Uses the same scenarios as Requirements. Choose an existing one when it applies.</p>
    <fieldset x-show="addingScenario" x-cloak :disabled="!addingScenario" class="mt-4 min-w-0 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium">Application type<select class="admin-input mt-1" x-model="scenarioDraft.application_type">@foreach(\App\Models\GovernmentIdRequirementSet::APPLICATION_TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm font-medium">Applicant type<select class="admin-input mt-1" x-model="scenarioDraft.applicant_type">@foreach(\App\Models\GovernmentIdRequirementSet::APPLICANT_TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label x-show="scenarioDraft.application_type === 'custom'" class="text-sm">Application name<input class="admin-input mt-1" maxlength="255" x-model="scenarioDraft.application_type_custom"></label>
            <label x-show="scenarioDraft.applicant_type === 'custom'" class="text-sm">Applicant label (optional)<input class="admin-input mt-1" maxlength="255" x-model="scenarioDraft.applicant_type_custom"></label>
            <label x-show="scenarioDraft.applicant_type === 'custom'" class="text-sm">Minimum age (optional)<input type="number" min="0" max="65535" step="1" class="admin-input mt-1" x-model="scenarioDraft.min_age"></label>
            <label x-show="scenarioDraft.applicant_type === 'custom'" class="text-sm">Maximum age (optional)<input type="number" min="0" max="65535" step="1" class="admin-input mt-1" x-model="scenarioDraft.max_age"></label>
        </div>
        <div class="mt-4 flex gap-2"><button type="button" class="admin-primary" @click="addScenario()">Use this scenario</button><button type="button" class="admin-secondary" @click="addingScenario = false; error = ''">Cancel</button></div>
    </fieldset>
    <template x-if="current">
        <div class="mt-5 space-y-3">
            <p x-show="!current.steps.length" class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">Start with the first thing this applicant needs to do.</p>
            <template x-for="(step, index) in current.steps" :key="selected + '-' + index">
                <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4">
                    <div class="min-w-0 flex-1"><p class="font-semibold text-slate-900"><span class="mr-2 text-slate-400" x-text="index + 1"></span><span x-text="step.title"></span></p><p class="mt-1 text-sm text-slate-500" x-show="step.short_description" x-text="step.short_description"></p><p class="mt-1 text-xs text-slate-400" x-text="step.blocks.length + ' information blocks'"></p></div>
                    <div class="flex flex-wrap gap-1"><button type="button" class="guide-tool" @click="moveStep(index, -1)" :disabled="index === 0" aria-label="Move step up">↑</button><button type="button" class="guide-tool" @click="moveStep(index, 1)" :disabled="index === current.steps.length - 1" aria-label="Move step down">↓</button><button type="button" class="guide-tool" @click="editStep(index)">Edit</button><button type="button" class="guide-tool text-red-700" @click="removeStep(index)">Remove</button></div>
                </article>
            </template>
            <button type="button" class="admin-secondary" @click="editStep()" :disabled="addingScenario">+ Add step</button>
            <p x-show="current.dirty" class="text-xs text-amber-700">Unsaved guide changes — save the ID to keep them.</p>
        </div>
    </template>
    <template x-teleport="body">
        <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @keydown.escape.stop.prevent="close()" @keydown="trap($event)">
            <div x-ref="dialog" role="dialog" aria-modal="true" aria-labelledby="guide-step-title" class="flex w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" style="max-width: 760px; max-height: 90dvh;">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4"><div><h2 id="guide-step-title" class="text-lg font-semibold" x-text="stepIndex < 0 ? 'Add step' : 'Edit step'"></h2><p class="text-xs text-slate-500" x-text="current ? label(current) : ''"></p></div><button type="button" class="guide-tool" @click="close()" aria-label="Close step editor">✕</button></div>
                <template x-if="draft">
                    <div class="min-h-0 space-y-5 overflow-y-auto p-6">
                        <p x-show="error" x-text="error" class="text-sm text-red-600" role="alert"></p>
                        <label class="block text-sm font-medium">Step title <span class="text-red-600">*</span><input x-ref="stepTitle" x-model="draft.title" maxlength="255" class="admin-input mt-2" placeholder="e.g. Book your appointment"></label>
                        <label class="block text-sm font-medium">Short description (optional)<textarea x-model="draft.short_description" maxlength="2000" rows="2" class="admin-input mt-2" placeholder="A short overview of this step."></textarea></label>
                        <label class="block text-sm font-medium">Step type<select x-model="draft.type" class="admin-input mt-2">@foreach(\App\Models\GovernmentIdApplicationStep::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                        <div class="space-y-3">
                            <h3 class="font-semibold">Information for this step</h3>
                            <template x-for="(block, blockIndex) in draft.blocks" :key="block.key">
                                <article class="overflow-hidden rounded-xl border border-slate-200">
                                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 p-3"><div><p class="text-sm font-semibold" x-text="block.section_title || blockNames[block.type]"></p><p x-show="block.section_title" class="text-xs text-slate-500" x-text="blockNames[block.type]"></p></div><div class="flex gap-1"><button type="button" class="guide-tool" @click="moveBlock(blockIndex, -1)" :disabled="blockIndex === 0" aria-label="Move information up">↑</button><button type="button" class="guide-tool" @click="moveBlock(blockIndex, 1)" :disabled="blockIndex === draft.blocks.length - 1" aria-label="Move information down">↓</button><button type="button" class="guide-tool" @click="activeBlock = activeBlock === blockIndex ? -1 : blockIndex" x-text="activeBlock === blockIndex ? 'Done' : 'Edit'"></button><button type="button" class="guide-tool text-red-700" @click="removeBlock(blockIndex)">Remove</button></div></div>
                                    <template x-if="activeBlock === blockIndex">
                                        <div class="space-y-4 p-4">
                                            <label class="block text-sm font-medium">Section title (optional)<input class="admin-input mt-1" x-model="block.section_title" maxlength="255" placeholder="e.g. Before you visit"></label>
                                            <template x-if="['instructions', 'checklist'].includes(block.type)">
                                                <div class="space-y-3">
                                                    <p class="text-xs text-slate-500">Add one item per row. Instructions are numbered automatically.</p>
                                                    <template x-for="(item, itemIndex) in block.content.items" :key="item.key">
                                                        <div class="rounded-lg border border-slate-200 p-3"><div class="mb-2 flex items-center justify-between"><span class="text-xs font-semibold" x-text="'Item ' + (itemIndex + 1)"></span><div class="flex gap-1"><button type="button" class="guide-tool" @click="move(block.content.items, itemIndex, -1)" :disabled="itemIndex === 0" aria-label="Move item up">↑</button><button type="button" class="guide-tool" @click="move(block.content.items, itemIndex, 1)" :disabled="itemIndex === block.content.items.length - 1" aria-label="Move item down">↓</button><button type="button" class="guide-tool" @click="block.content.items.splice(itemIndex, 1)">Remove</button></div></div>
                                                            @include('admin.government_ids.partials.guide-rich-text', ['value' => 'item.body'])
                                                        </div>
                                                    </template>
                                                    <button type="button" class="admin-secondary" @click="addItem(block)">+ Add item</button>
                                                </div>
                                            </template>
                                            <template x-if="block.type === 'official_link'"><div class="space-y-3"><label class="block text-sm">Link label<input class="admin-input mt-1" x-model="block.content.label" maxlength="255" placeholder="Open the official appointment website"></label><label class="block text-sm">Official URL<input type="url" class="admin-input mt-1" x-model="block.content.url" maxlength="2048" placeholder="https://..."></label><label class="block text-sm">Description (optional)<textarea class="admin-input mt-1" x-model="block.content.description" maxlength="2000" rows="2"></textarea></label></div></template>
                                            <template x-if="['reminder', 'custom'].includes(block.type)"><div><p class="mb-2 text-sm font-medium">Content</p>@include('admin.government_ids.partials.guide-rich-text', ['value' => 'block.content.body'])</div></template>
                                            <template x-if="['requirements', 'fees', 'offices', 'processing_time'].includes(block.type)"><div><p class="mb-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-900" x-text="block.type === 'requirements' ? 'Shows the requirements for this applicant/application scenario.' : 'Shows the current ' + blockNames[block.type].toLowerCase() + ' saved on this ID.'"></p><p class="mb-2 text-sm font-medium">Introduction (optional)</p>@include('admin.government_ids.partials.guide-rich-text', ['value' => 'block.content.intro'])<p class="mt-2 text-xs text-slate-500">Updates automatically when the original information changes.</p></div></template>
                                        </div>
                                    </template>
                                </article>
                            </template>
                            <button type="button" class="admin-secondary" @click="choosingBlock = !choosingBlock">+ Add information</button>
                            <div x-show="choosingBlock" class="grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-2"><template x-for="(name, type) in blockNames" :key="type"><button type="button" class="rounded-lg border border-slate-200 bg-white p-3 text-left text-sm hover:border-blue-500" @click="addBlock(type)" x-text="name"></button></template></div>
                        </div>
                    </div>
                </template>
                <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-6 py-4"><p class="text-xs text-slate-500">Apply, then save the main form.</p><div class="flex gap-2"><button type="button" class="admin-secondary" @click="close()">Cancel</button><button type="button" class="admin-primary" @click="applyStep()">Apply step</button></div></div>
            </div>
        </div>
    </template>
</section>
