<div class="space-y-5 border-t border-slate-200 pt-4" aria-label="Application step editor">
    <label class="block text-sm font-medium">{{ ($wizardGuide ?? false) ? 'Step Title' : 'Step Title — What should the applicant do?' }}
        <input data-guide-title x-model="draft.title" maxlength="255" class="admin-input mt-2" placeholder="e.g. Book your appointment" :aria-invalid="!!fieldErrors.title" :aria-describedby="'guide-title-error-' + draft.key">
    </label>
    <p :id="'guide-title-error-' + draft.key" x-show="fieldErrors.title" x-text="fieldErrors.title" role="alert" class="text-sm text-red-700"></p>
    <label class="block text-sm font-medium">{{ ($wizardGuide ?? false) ? 'Instructions' : 'Instructions — Explain what the applicant needs to do.' }}
        <textarea x-model="draft.short_description" maxlength="2000" rows="{{ ($wizardGuide ?? false) ? 2 : 4 }}" class="admin-input mt-2" placeholder="e.g. Choose an available date on the official appointment website. Keep your confirmation number." :aria-invalid="!!fieldErrors.instructions" :aria-describedby="'guide-instructions-help-' + draft.key + ' guide-instructions-error-' + draft.key"></textarea>
    </label>
    <p :id="'guide-instructions-help-' + draft.key" class="text-xs text-slate-500">Up to 2,000 characters. For longer instructions, links, or formatting, open More Content &amp; Settings.</p>
    <p :id="'guide-instructions-error-' + draft.key" x-show="fieldErrors.instructions" x-text="fieldErrors.instructions" role="alert" class="text-sm text-red-700"></p>
    <details :open="advancedOpen" @toggle="advancedOpen = $el.open" class="rounded-lg border border-slate-200 p-4">
        <summary class="cursor-pointer text-sm font-medium">More Content &amp; Settings <span class="text-slate-500" x-text="draft.blocks.length ? '(' + draft.blocks.length + ' saved or draft sections)' : '(optional)'"></span></summary>
        <div class="mt-4 space-y-5">
            @if($wizardGuide ?? false)
                <details :open="optionalLinkOpen" @toggle="optionalLinkOpen = $el.open" class="rounded-lg border border-slate-200 p-4">
                    <summary class="cursor-pointer text-sm font-medium">Optional Link</summary>
                    <div class="mt-4 space-y-4">
                        <label class="block text-sm font-medium">Link Label
                            <input x-model="simpleOfficialLabel" maxlength="255" class="admin-input mt-2" placeholder="e.g. DFA Passport Appointment System">
                        </label>
                        <label class="block text-sm font-medium">URL
                            <input type="text" inputmode="url" x-model="simpleOfficialUrl" maxlength="2048" class="admin-input mt-2" placeholder="https://passport.gov.ph"
                                :aria-invalid="!!fieldErrors['block-' + simpleLinkKey]" :aria-describedby="'guide-link-error-' + draft.key">
                        </label>
                        <p :id="'guide-link-error-' + draft.key" x-show="fieldErrors['block-' + simpleLinkKey]" x-text="fieldErrors['block-' + simpleLinkKey]" role="alert" class="text-sm text-red-700"></p>
                    </div>
                </details>
                <details :open="reminderOpen" @toggle="reminderOpen = $el.open" class="rounded-lg border border-slate-200 p-4">
                    <summary class="cursor-pointer text-sm font-medium">Reminder / Note</summary>
                    <div class="mt-4">
                        <template x-if="simpleReminderSupported">
                            <label class="block text-sm font-medium">Reminder / Note
                                <textarea x-model="simpleReminderText" maxlength="20000" rows="2" class="admin-input mt-2" placeholder="e.g. Bring your confirmed appointment receipt."></textarea>
                            </label>
                        </template>
                        <p x-show="!simpleReminderSupported" class="text-sm text-slate-500">This step has a formatted reminder. Use its Edit action in Additional content below to keep its formatting.</p>
                    </div>
                </details>
                <section aria-label="Use existing ID information" class="rounded-lg bg-blue-50 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Use existing ID information</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-600">These blocks use the information already saved for this Government ID. You do not need to type it again.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach(['requirements' => 'Requirements', 'fees' => 'Fees', 'offices' => 'Offices', 'processing_time' => 'Processing Time'] as $type => $label)
                            <button type="button" class="admin-secondary" @click="addBlock('{{ $type }}')"
                                :aria-pressed="hasReference('{{ $type }}')" :disabled="hasReference('{{ $type }}') || draft.blocks.length >= 50"
                                x-text="(hasReference('{{ $type }}') ? '✓ ' : '+ ') + @js($label)">+ {{ $label }}</button>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">These use the current Government ID data and stay connected to it.</p>
                    <p x-show="duplicateReferenceLabels.length" role="status" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900"
                        x-text="'Duplicate references: ' + duplicateReferenceLabels.join(', ') + '. Saved blocks are kept. Remove extra rows below; the app preview shows each reference once.'"></p>
                    <div class="mt-4 space-y-3">
                        <template x-for="entry in referenceEntries" :key="entry.block.key">
                            <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-slate-900" x-text="liveReferenceNames[entry.block.type] + ' reference'"></h4>
                                    <button type="button" class="text-sm text-red-700" @click="removeBlock(entry.index)" :aria-label="'Remove ' + liveReferenceNames[entry.block.type] + ' reference'">Remove</button>
                                </div>
                                <label class="block text-sm font-medium">Display title (optional)
                                    <input x-model="entry.block.section_title" maxlength="255" class="admin-input mt-1" :placeholder="liveReferenceNames[entry.block.type]">
                                </label>
                                <details class="rounded-lg border border-slate-200 p-3">
                                    <summary class="cursor-pointer text-sm text-slate-600">Introduction (optional)</summary>
                                    <div class="mt-3">@include('admin.government_ids.partials.guide-rich-text', ['value' => 'entry.block.content.intro'])</div>
                                </details>
                            </div>
                        </template>
                    </div>
                </section>
            @endif
                        <label class="block text-sm font-medium">Step type (optional)<select x-model="draft.type" class="admin-input mt-2">@foreach(\App\Models\GovernmentIdApplicationStep::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                        <div class="space-y-3">
                            <h3 class="font-semibold">Additional content</h3>
                            @if($wizardGuide ?? false)
                                <p class="text-sm text-slate-500">Add instructions, checklists, reminders, links, or custom information here. Live ID references are managed above.</p>
                            @else
                                <p class="text-sm text-slate-500">Use Linked Requirements to display the requirements already configured for this applicant/application. Procedural checklists describe actions; they do not create ID or document dependencies.</p>
                            @endif
                            <template x-for="(block, blockIndex) in draft.blocks" :key="block.key">
                                @if($wizardGuide ?? false)<template x-if="!isLiveReference(block.type)">@endif
                                <article class="overflow-hidden rounded-xl border border-slate-200">
                                    <p x-show="fieldErrors['block-' + block.key]" x-text="fieldErrors['block-' + block.key]" role="alert" class="p-3 text-sm text-red-700"></p>
                                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 p-3"><div><p class="text-sm font-semibold" x-text="block.section_title || blockNames[block.type]"></p><p x-show="block.section_title" class="text-xs text-slate-500" x-text="blockNames[block.type]"></p></div><div class="flex gap-1"><button type="button" class="guide-tool" @click="moveBlock(blockIndex, -1)" :disabled="blockIndex === 0" aria-label="Move information up">↑</button><button type="button" class="guide-tool" @click="moveBlock(blockIndex, 1)" :disabled="blockIndex === draft.blocks.length - 1" aria-label="Move information down">↓</button><button type="button" class="guide-tool" @click="activeBlock = activeBlock === blockIndex ? -1 : blockIndex" x-text="activeBlock === blockIndex ? 'Done' : 'Edit'"></button><button type="button" class="guide-tool text-red-700" @click="removeBlock(blockIndex)">Remove</button></div></div>
                                    <template x-if="activeBlock === blockIndex">
                                        <div class="space-y-4 p-4">
                                            <label class="block text-sm font-medium">Content type
                                                <select class="admin-input mt-1" :value="block.type" @change="changeBlockType(block, $event.target.value); $event.target.value = block.type">
                                                    <template x-if="!availableBlockNames[block.type]"><option :value="block.type" x-text="blockNames[block.type] + ' (existing)'"></option></template>
                                                    <template x-for="(name, type) in contentBlockNames" :key="type"><option :value="type" x-text="name"></option></template>
                                                </select>
                                            </label>
                                            <label class="block text-sm font-medium">Display title (optional)<input class="admin-input mt-1" x-model="block.section_title" maxlength="255" :placeholder="block.type === 'requirements' ? 'Required Documents' : 'e.g. Before you visit'"></label>
                                            <template x-if="['instructions', 'checklist'].includes(block.type)">
                                                <div class="space-y-3">
                                                    <p class="text-xs text-slate-500">Add one item per row. Instructions are numbered automatically.</p>
                                                    <template x-for="(item, itemIndex) in block.content.items" :key="item.key">
                                                        <div class="rounded-lg border border-slate-200 p-3"><div class="mb-2 flex items-center justify-between"><span class="text-xs font-semibold" x-text="'Item ' + (itemIndex + 1)"></span><div class="flex gap-1"><button type="button" class="guide-tool" @click="move(block.content.items, itemIndex, -1)" :disabled="itemIndex === 0" aria-label="Move item up">↑</button><button type="button" class="guide-tool" @click="move(block.content.items, itemIndex, 1)" :disabled="itemIndex === block.content.items.length - 1" aria-label="Move item down">↓</button><button type="button" class="guide-tool" @click="removeItem(block, itemIndex)">Remove</button></div></div>
                                                            <template x-if="block.type === 'checklist' && typeof item.body === 'string'"><label class="block text-sm">Checklist item<input class="admin-input mt-1" x-model="item.body" maxlength="20000"></label></template>
                                                            <template x-if="block.type === 'instructions' || typeof item.body !== 'string'"><div>@include('admin.government_ids.partials.guide-rich-text', ['value' => 'item.body'])</div></template>
                                                        </div>
                                                    </template>
                                                    <button type="button" class="admin-secondary" @click="addItem(block)" :disabled="block.content.items.length >= 100">+ Add item</button>
                                                </div>
                                            </template>
                                            <template x-if="block.type === 'official_link'"><div class="space-y-3"><label class="block text-sm">Link label<input class="admin-input mt-1" x-model="block.content.label" maxlength="255" placeholder="Open the official appointment website"></label><label class="block text-sm">Official URL<input type="url" class="admin-input mt-1" x-model="block.content.url" maxlength="2048" placeholder="https://..."></label><label class="block text-sm">Description (optional)<textarea class="admin-input mt-1" x-model="block.content.description" maxlength="2000" rows="2"></textarea></label></div></template>
                                            <template x-if="['reminder', 'custom'].includes(block.type)"><div><p class="mb-2 text-sm font-medium">Content</p>@include('admin.government_ids.partials.guide-rich-text', ['value' => 'block.content.body'])</div></template>
                                            <template x-if="['requirements', 'fees', 'offices', 'processing_time'].includes(block.type)"><div><p class="mb-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-900" x-text="block.type === 'requirements' ? 'Uses the requirements already configured for this applicant/application. Edit them in Requirements; changes appear here automatically.' : 'Shows the current ' + blockNames[block.type].toLowerCase() + ' saved on this entry.'"></p><p class="mb-2 text-sm font-medium">Introduction (optional)</p>@include('admin.government_ids.partials.guide-rich-text', ['value' => 'block.content.intro'])<p class="mt-2 text-xs text-slate-500">Updates automatically when the original information changes.</p></div></template>
                                        </div>
                                    </template>
                                </article>
                                @if($wizardGuide ?? false)</template>@endif
                            </template>
                            <button type="button" class="admin-secondary" @click="choosingBlock = !choosingBlock" :disabled="draft.blocks.length >= 50">+ Add more content</button>
                            <div x-show="choosingBlock" class="grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-2"><template x-for="(name, type) in contentBlockNames" :key="type"><button type="button" class="rounded-lg border border-slate-200 bg-white p-3 text-left text-sm hover:border-blue-500" @click="addBlock(type)" x-text="name"></button></template></div>
                        </div>
        </div>
    </details>
    <p class="text-xs text-slate-500">{{ ($wizardGuide ?? false) ? 'Changes are saved when you continue.' : 'Apply step keeps your changes in this form. Save the main entry to store them.' }}</p>
    <div class="flex flex-wrap justify-end gap-3">
        <button type="button" class="admin-secondary" @click="close()">{{ ($wizardGuide ?? false) ? 'Cancel' : 'Cancel step changes' }}</button>
        <button type="button" class="admin-primary" @click="applyStep()">{{ ($wizardGuide ?? false) ? 'Done' : 'Apply step' }}</button>
    </div>
</div>
