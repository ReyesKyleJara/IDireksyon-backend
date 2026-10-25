@php
    $guideRows = app(\App\Services\GovernmentIdApplicationGuide::class)->present($governmentId ?? null);
    $guideConfig = [
        'deferred' => $deferred ?? false,
        'scenarios' => $guideRows,
        'oldPayload' => is_string(old('application_guide_payload')) ? old('application_guide_payload') : null,
    ];
@endphp
<section class="admin-panel p-6" aria-label="Application Guide" x-data="governmentIdGuide(@js($guideConfig))" @guide-scenario-changed.window="scenarioChanged($event.detail)">
    <input type="hidden" x-ref="guidePayload" name="application_guide_payload" :disabled="!ready" :value="payload" disabled>
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
    <p x-ref="guideError" tabindex="-1" x-show="error" x-text="error" class="mt-3 text-sm text-red-600" role="alert"></p>
    <div class="mt-5 flex flex-wrap items-end gap-3">
        <label class="min-w-0 flex-1 text-sm font-medium">View steps for
            <select x-model="selected" class="admin-input mt-2" :disabled="!scenarios.length || !!draft || addingScenario || !!stepDrag">
                <option value="" x-show="!scenarios.length">Add an applicant/application scenario</option>
                <template x-for="scenario in scenarios" :key="scenario.key"><option :value="scenario.key" x-text="label(scenario)"></option></template>
            </select>
        </label>
        <button type="button" class="admin-secondary" @click="startScenario()" :disabled="addingScenario || !!draft || !!stepDrag">+ Add scenario</button>
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
        <div class="mt-5 space-y-4">
            <p id="guide-reorder-help" class="text-xs text-slate-500">Drag a card to reorder. With its header focused, press Alt + ↑ or ↓. Press Escape to cancel dragging.</p>
            <p x-show="!current.steps.length && !draft" class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">Start with the first thing this applicant needs to do.</p>
            <template x-for="(step, index) in current.steps" :key="step.key">
                <article :data-guide-card="step.key" class="relative min-w-0 rounded-xl border border-slate-200 bg-white"
                    :class="{ 'opacity-50 ring-2 ring-blue-400': stepDrag?.active && stepDrag.key === step.key, 'border-t-4 border-t-blue-600': stepDrag?.active && stepDrag.target === step.key && stepDrag.key !== step.key && !stepDrag.after, 'border-b-4 border-b-blue-600': stepDrag?.active && stepDrag.target === step.key && stepDrag.key !== step.key && stepDrag.after }">
                    <div tabindex="0" role="group" :aria-label="'Step ' + (index + 1) + ': ' + step.title" aria-roledescription="reorderable step" aria-describedby="guide-reorder-help"
                        :data-guide-header="step.key" class="flex select-none items-center gap-3 rounded-xl p-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700" style="touch-action: none;"
                        :class="stepDrag?.key === step.key ? 'cursor-grabbing' : 'cursor-grab'"
                        @pointerdown="startStepDrag($event, step.key)" @pointermove="moveStepDrag($event)" @pointerup="endStepDrag($event)"
                        @pointercancel="cancelStepDrag()" @lostpointercapture="cancelStepDrag()" @keydown="cardKeydown($event, index)" @click="finishCardClick($event)">
                        <span aria-hidden="true" class="shrink-0 text-lg text-slate-400">⋮⋮</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="break-words text-sm font-semibold text-slate-900"><span x-text="(index + 1) + '. '"></span><span x-text="step.title"></span></h3>
                            <p class="mt-1 truncate text-sm text-slate-500" x-text="summary(step)"></p>
                        </div>
                        <button type="button" class="guide-tool flex min-h-10 min-w-10 shrink-0 items-center justify-center" @click.stop="draft && stepIndex === index ? applyStep() : editStep(index)"
                            :disabled="!!stepDrag || (!!draft && stepIndex !== index)" :aria-label="(draft && stepIndex === index ? 'Save and collapse step ' : 'Expand step ') + (index + 1)" :aria-expanded="!!draft && stepIndex === index"
                            :aria-controls="'guide-step-' + step.key" :data-guide-step="step.key"><span aria-hidden="true" x-text="draft && stepIndex === index ? '⌃' : '⌄'"></span></button>
                        <div class="relative shrink-0" @click.outside="if (openMenuKey === step.key) closeMenu(false)" @keydown.escape.stop.prevent="closeMenu()">
                            <button type="button" class="guide-tool flex min-h-10 min-w-10 items-center justify-center text-xl" @click.stop="toggleMenu(step.key)" :disabled="!!stepDrag"
                                :aria-label="'Actions for step ' + (index + 1)" aria-haspopup="menu" :aria-expanded="openMenuKey === step.key" :aria-controls="'guide-menu-' + step.key" :data-step-menu-trigger="step.key"><span aria-hidden="true">⋮</span></button>
                            <div x-show="openMenuKey === step.key" x-cloak :id="'guide-menu-' + step.key" :data-step-menu="step.key" role="menu" :aria-label="'Actions for step ' + (index + 1)"
                                class="absolute right-0 top-full z-20 mt-1 w-36 rounded-lg border border-slate-200 bg-white p-1 shadow-lg" @keydown="menuKeydown($event)">
                                <button type="button" role="menuitem" class="guide-tool block w-full p-2 text-left" :disabled="!!draft && stepIndex !== index" @click.stop="closeMenu(false); editMenuStep(index)">Edit</button>
                                <button type="button" role="menuitem" class="guide-tool block w-full p-2 text-left text-red-700" :disabled="!!draft" @click.stop="closeMenu(); removeStep(index)">Delete</button>
                            </div>
                        </div>
                    </div>
                    <div :id="'guide-step-' + step.key" class="px-4" :class="draft && stepIndex === index ? 'pb-4' : ''">
                        <template x-if="draft && stepIndex === index"><div>@include('admin.government_ids.partials.application-guide-step-editor')</div></template>
                    </div>
                </article>
            </template>
            <template x-if="draft && stepIndex === -1">
                <article class="min-w-0 rounded-xl border border-blue-400 p-4">
                    <h3 class="mb-4 font-semibold" x-text="'Step ' + (current.steps.length + 1) + '. New application step'"></h3>
                    @include('admin.government_ids.partials.application-guide-step-editor')
                </article>
            </template>
            <button type="button" x-ref="addStepButton" class="admin-secondary" @click="editStep()" :disabled="!!draft || !!stepDrag || addingScenario || current.steps.length >= 100">+ Add Application Step</button>
            <p class="sr-only" role="status" aria-live="polite" x-text="dragStatus"></p>
            <p x-show="stepDrag?.active" class="text-sm font-medium text-blue-800">Dragging — release at the highlighted edge. Press Escape to cancel.</p>
            <p x-show="current.dirty || draft" class="text-sm text-amber-700" role="status">Unsaved guide changes — save the main entry to keep them.</p>
            <p class="text-xs text-slate-500">Step changes, deletions, and order are saved with the main form. Saving Requirements separately does not save this guide.</p>
        </div>
    </template>
</section>
