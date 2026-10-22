<div class="space-y-5 border-t border-slate-200 pt-4" aria-label="Application step editor">
    <label class="block text-sm font-medium">Applies To
        <select x-model="draftAppliesTo" class="admin-input mt-2">
            <template x-for="profile in scenarios" :key="profile.key"><option :value="profile.key" x-text="label(profile)"></option></template>
        </select>
    </label>
    <label class="block text-sm font-medium">Guide position (starts at 0)
        <input type="number" min="0" max="4294967295" step="1" x-model="draft.sort_order" class="admin-input mt-2" placeholder="Uses position in this list when blank">
    </label>
    <p class="text-xs text-slate-500">Shared and profile steps merge by this position. At equal positions, shared steps come first.</p>
    <label class="block text-sm font-medium">Step Title — What should the applicant do?
        <input data-guide-title x-model="draft.title" maxlength="255" class="admin-input mt-2" placeholder="e.g. Book your appointment" :aria-invalid="!!fieldErrors.title" :aria-describedby="'guide-title-error-' + draft.key">
    </label>
    <p :id="'guide-title-error-' + draft.key" x-show="fieldErrors.title" x-text="fieldErrors.title" role="alert" class="text-sm text-red-700"></p>
    <label class="block text-sm font-medium">Instructions — Explain what the applicant needs to do.
        <textarea x-model="draft.short_description" maxlength="2000" rows="4" class="admin-input mt-2" placeholder="e.g. Choose an available date on the official appointment website. Keep your confirmation number." :aria-invalid="!!fieldErrors.instructions" :aria-describedby="'guide-instructions-help-' + draft.key + ' guide-instructions-error-' + draft.key"></textarea>
    </label>
    <p :id="'guide-instructions-help-' + draft.key" class="text-xs text-slate-500">Up to 2,000 characters. For longer instructions, links, or formatting, open More Content &amp; Settings.</p>
    <p :id="'guide-instructions-error-' + draft.key" x-show="fieldErrors.instructions" x-text="fieldErrors.instructions" role="alert" class="text-sm text-red-700"></p>
    <details :open="advancedOpen" @toggle="advancedOpen = $el.open" class="rounded-lg border border-slate-200 p-4">
        <summary class="cursor-pointer text-sm font-medium">More Content &amp; Settings <span class="text-slate-500" x-text="draft.blocks.length ? '(' + draft.blocks.length + ' saved or draft sections)' : '(optional)'"></span></summary>
        <div class="mt-4 space-y-5">
                        <label class="block text-sm font-medium">Step type (optional)<select x-model="draft.type" class="admin-input mt-2">@foreach(\App\Models\GovernmentIdApplicationStep::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                        <div class="space-y-3">
                            <h3 class="font-semibold">Additional content</h3>
                            <p class="text-sm text-slate-500">Use Linked Requirements to display the requirements already configured for this applicant/application. Procedural checklists describe actions; they do not create ID or document dependencies.</p>
                            <template x-for="(block, blockIndex) in draft.blocks" :key="block.key">
                                <article class="overflow-hidden rounded-xl border border-slate-200">
                                    <p x-show="fieldErrors['block-' + block.key]" x-text="fieldErrors['block-' + block.key]" role="alert" class="p-3 text-sm text-red-700"></p>
                                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 p-3"><div><p class="text-sm font-semibold" x-text="block.section_title || blockNames[block.type]"></p><p x-show="block.section_title" class="text-xs text-slate-500" x-text="blockNames[block.type]"></p></div><div class="flex gap-1"><button type="button" class="guide-tool" @click="moveBlock(blockIndex, -1)" :disabled="blockIndex === 0" aria-label="Move information up">↑</button><button type="button" class="guide-tool" @click="moveBlock(blockIndex, 1)" :disabled="blockIndex === draft.blocks.length - 1" aria-label="Move information down">↓</button><button type="button" class="guide-tool" @click="activeBlock = activeBlock === blockIndex ? -1 : blockIndex" x-text="activeBlock === blockIndex ? 'Done' : 'Edit'"></button><button type="button" class="guide-tool text-red-700" @click="removeBlock(blockIndex)">Remove</button></div></div>
                                    <template x-if="activeBlock === blockIndex">
                                        <div class="space-y-4 p-4">
                                            <label class="block text-sm font-medium">Content type
                                                <select class="admin-input mt-1" :value="block.type" @change="changeBlockType(block, $event.target.value); $event.target.value = block.type">
                                                    <template x-if="!availableBlockNames[block.type]"><option :value="block.type" x-text="blockNames[block.type] + ' (existing)'"></option></template>
                                                    <template x-for="(name, type) in availableBlockNames" :key="type"><option :value="type" x-text="name"></option></template>
                                                </select>
                                            </label>
                                            <template x-if="['text', 'heading', 'note', 'warning'].includes(block.type)">
                                                <label class="block text-sm font-medium">Content<textarea class="admin-input mt-1" x-model="block.content.body" :maxlength="block.type === 'heading' ? 255 : 20000" :rows="block.type === 'heading' ? 2 : 3"></textarea></label>
                                            </template>
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
                            </template>
                            <button type="button" class="admin-secondary" @click="choosingBlock = !choosingBlock" :disabled="draft.blocks.length >= 50">+ Add more content</button>
                            <div x-show="choosingBlock" class="grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-2"><template x-for="(name, type) in availableBlockNames" :key="type"><button type="button" class="rounded-lg border border-slate-200 bg-white p-3 text-left text-sm hover:border-blue-500" @click="addBlock(type)" x-text="name"></button></template></div>
                        </div>
        </div>
    </details>
    <p class="text-xs text-slate-500">Save step keeps your changes in this form. Save the main entry to store them.</p>
    <div class="flex flex-wrap justify-end gap-3">
        <button type="button" class="admin-secondary" @click="close()">Cancel step changes</button>
        <button type="button" class="admin-primary" @click="applyStep()">Save step</button>
    </div>
</div>
