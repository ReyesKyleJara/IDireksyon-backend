<section @unless($embeddedGuide ?? false) id="application-steps" @endunless class="grid min-w-0 items-start gap-6 {{ ($embeddedGuide ?? false) ? '' : '2xl:grid-cols-[minmax(0,1fr)_340px]' }}" aria-label="Application Guide" x-data="governmentIdGuide(@js($guideConfig))" @guide-scenario-changed.window="if (!@js($embeddedGuide ?? false)) scenarioChanged($event.detail)">
    <div class="admin-panel min-w-0 p-6">
    <input type="hidden" x-ref="guidePayload" name="application_guide_payload" :disabled="!ready" :value="payload" disabled>
    <fieldset :disabled="saving" class="min-w-0">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="text-lg font-semibold text-slate-900">Application steps</h2><p class="mt-1 text-sm text-slate-500">{{ ($embeddedGuide ?? false) ? 'Write the steps for this special case. These do not change the normal request guides.' : 'Write the usual steps for the request option selected above. Keep problems and exceptions in Special cases.' }}</p></div>
        <span class="text-xs text-slate-500">{{ ($embeddedGuide ?? false) ? 'Save all sections with Save special case guide.' : 'Save these steps with Continue below.' }}</span>
    </div>
    <noscript><p class="mt-3 text-sm text-amber-700">Enable JavaScript to edit the Application Guide. Existing steps will be preserved.</p></noscript>
    @foreach($errors->getMessages() as $field => $messages)
        @if(str_starts_with($field, 'application_guide'))
            @foreach($messages as $message)<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@endforeach
        @endif
    @endforeach
    <p x-ref="guideError" tabindex="-1" x-show="error" x-text="error" class="mt-3 whitespace-pre-line text-sm text-red-600" role="alert"></p>
    @if(count($guideRows) > 1)
        <label class="mt-5 block text-sm font-medium">Application checklist
            <select x-model="selected" class="admin-input mt-2" :disabled="!!draft || !!stepDrag || saving">
                <template x-for="scenario in scenarios" :key="scenario.key"><option :value="scenario.key" x-text="label(scenario)"></option></template>
            </select>
        </label>
    @endif
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
                            <p class="text-xs font-medium text-slate-500" x-text="'Step ' + (index + 1)"></p>
                            <h3 class="mt-1 break-words text-sm font-semibold text-slate-900" x-text="step.title"></h3>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-600" x-text="summary(step)"></p>
                            <p x-show="officialLinkHost(step)" class="mt-2 break-words text-xs text-blue-800" x-text="officialLinkHost(step)"></p>
                        </div>
                        <button type="button" class="guide-tool flex min-h-10 min-w-10 shrink-0 items-center justify-center" @click.stop="draft && stepIndex === index ? applyStep() : editStep(index)"
                            :disabled="!!stepDrag || (!!draft && stepIndex !== index)" :aria-label="(draft && stepIndex === index ? 'Finish step ' : 'Edit step ') + (index + 1)" :aria-expanded="!!draft && stepIndex === index"
                            :aria-controls="'guide-step-' + step.key" :data-guide-step="step.key"><span x-text="draft && stepIndex === index ? 'Done' : 'Edit'"></span></button>
                        <button type="button" class="guide-tool shrink-0 text-sm text-red-700" :disabled="!!draft || !!stepDrag"
                            @click.stop="removeStep(index)" :aria-label="'Delete step ' + (index + 1)">Delete</button>
                    </div>
                    <div :id="'guide-step-' + step.key" class="px-4" :class="draft && stepIndex === index ? 'pb-4' : ''">
                        <template x-if="draft && stepIndex === index"><div>@include('admin.government_ids.partials.application-guide-step-editor', ['wizardGuide' => true])</div></template>
                    </div>
                </article>
            </template>
            <template x-if="draft && stepIndex === -1">
                <article class="min-w-0 rounded-xl border border-blue-400 p-4">
                    <h3 class="mb-4 font-semibold" x-text="'Step ' + (current.steps.length + 1) + '. New application step'"></h3>
                    @include('admin.government_ids.partials.application-guide-step-editor', ['wizardGuide' => true])
                </article>
            </template>
            <button type="button" x-ref="addStepButton" class="admin-secondary" @click="editStep()" :disabled="!!draft || !!stepDrag || addingScenario || current.steps.length >= 100">+ Add Application Step</button>
            <p class="sr-only" role="status" aria-live="polite" x-text="dragStatus"></p>
            <p x-show="stepDrag?.active" class="text-sm font-medium text-blue-800">Dragging — release at the highlighted edge. Press Escape to cancel.</p>
            <p x-show="current.dirty || draft" class="text-sm text-amber-700" role="status">{{ ($embeddedGuide ?? false) ? 'Unsaved guide changes — save this special case to keep them.' : 'Unsaved guide changes — continue to save them.' }}</p>
            <p class="text-xs text-slate-500">{{ ($embeddedGuide ?? false) ? 'Save special case guide saves the steps, content, and order with the other sections.' : 'Continue saves your steps, content, and order, then opens Offices.' }}</p>
        </div>
    </template>
    </fieldset>
    </div>
    @unless($embeddedGuide ?? false)
    @include('admin.government_ids.wizard.app-preview')
    @endunless
</section>
