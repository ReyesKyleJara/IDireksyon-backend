<section class="space-y-5" aria-label="Requirement editor">
    <h4 class="font-semibold" x-text="editingIndex === null ? 'New requirement' : 'Edit requirement'"></h4>
    <label class="block text-sm"><input type="checkbox" x-model="editing.conditional" class="mr-2">Only required in a particular situation</label>
    <div x-show="editing.conditional" class="space-y-3">
                <label class="block text-sm font-medium">When is it required?
                    <select x-model="editing.condition_type" class="admin-input mt-1">
                        <option value="always">Select a situation</option>
                        @foreach(\App\Models\GovernmentIdRequirementGroup::CONDITIONS as $key => $label)
                            @if($key !== 'always')
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs font-normal text-slate-500">This requirement will only be considered when this situation applies.</span>
                </label>
                <label x-show="editing.condition_type === 'custom'" class="block text-sm font-medium">Describe the situation
                    <textarea x-model="editing.condition_custom" maxlength="1000" rows="2" class="admin-input mt-1"></textarea>
                </label>
    </div>

    <label class="block text-sm font-medium">
        Requirement name (optional)
        <input x-model="editing.title" maxlength="255" placeholder="e.g. Valid ID or Proof of Identity" class="admin-input mt-2">
    </label>

    <div x-show="editing.ways.length > 1" class="rounded-lg border border-slate-200 p-4">
        <p class="text-sm font-medium text-slate-800">The applicant can use any one of these alternatives.</p>
        <template x-for="(way, index) in editing.ways" :key="way.key">
            <p class="mt-2 text-sm text-slate-600" x-text="(index > 0 ? 'OR — ' : '') + waySummary(way)"></p>
        </template>
    </div>
    <p x-show="editing.conditional && editing.condition_type !== 'always'" class="text-sm text-slate-600" x-text="'Only if: ' + conditionLabel(editing)"></p>

    <template x-for="(way, wayIndex) in editing.ways" :key="way.key">
        <div class="space-y-4">
            <div x-show="editing.ways.length > 1" class="flex items-center justify-between border-t border-slate-200 pt-4">
                <p class="text-sm font-semibold" x-text="wayIndex === 0 ? 'Alternative 1' : 'OR — Alternative ' + (wayIndex + 1)"></p>
                <button type="button" class="text-xs text-red-700" @click="removeWay(wayIndex)">Remove alternative</button>
            </div>

            <label class="block text-sm font-medium">What must they provide?
                <select class="admin-input mt-2" :value="way.mode" @change="selection(way, $event.target.value); $event.target.value = way.mode">
                    <option value="specific" :disabled="way.items.length > 1 || Number(way.required_count) !== 1">One item</option>
                    <option value="all">All listed items</option>
                    <option value="accepted">Choose N accepted items</option>
                </select>
            </label>
            <label x-show="way.mode === 'accepted'" class="block text-sm font-medium">How many accepted items are needed?
                <input type="number" min="1" max="50" step="1" :value="way.required_count" @change="setCount(way, $event.target.value); $event.target.value = way.required_count" class="admin-input mt-2">
            </label>
            <p x-show="editing.legacy" class="text-xs text-slate-500">Original all/choose-one storage is preserved. Choosing more than one accepted item or adding an OR alternative requires explicit conversion.</p>

            <div data-requirement-search x-show="way.mode !== 'specific' || way.items.length === 0">
                <label class="block text-sm font-medium">
                    <span x-text="way.mode !== 'specific' ? 'Accepted items' : 'What does the applicant need?'"></span>
                    <input type="search" x-model="way.search" placeholder="Search IDs, permits, or documents…" autocomplete="off" class="admin-input mt-2">
                </label>
                <p class="mt-1 text-xs text-slate-500" x-text="way.mode !== 'specific' ? 'Use + to add accepted items. Your search stays open so you can keep adding.' : 'Start typing a name, such as Student Permit or Birth Certificate.'"></p>
                <div x-show="way.search.trim()" class="mt-2 max-h-44 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1">
                    <template x-for="option in matches(way)" :key="option.type + ':' + option.id">
                        <div class="flex items-center justify-between gap-3 rounded px-3 py-2 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="break-words text-slate-800" x-text="option.name"></p>
                                <p class="mt-1 text-xs text-slate-500" x-text="option.type === 'government_id' ? 'Government ID / Permit' : 'Document'"></p>
                            </div>
                            <button type="button" @click="addItem(way, option, $event)"
                                :disabled="isSelected(way, option) || way.items.length >= 50"
                                :aria-label="(isSelected(way, option) ? 'Added ' : (way.mode !== 'specific' ? 'Add ' : 'Select ')) + option.name"
                                class="flex min-h-10 min-w-10 shrink-0 items-center justify-center rounded-md border border-slate-200 px-2 text-[#012877] hover:bg-blue-50 disabled:cursor-default disabled:border-transparent disabled:text-slate-500 disabled:hover:bg-transparent">
                                <span x-show="!isSelected(way, option)" class="text-sm font-medium" x-text="way.mode !== 'specific' ? '+ Add' : 'Select'"></span>
                                <span x-show="isSelected(way, option)" class="text-xs">✓ Added</span>
                            </button>
                        </div>
                    </template>
                    <p x-show="matches(way).length === 0" class="p-3 text-xs text-slate-500">No matches. Try another name, or add a custom item below.</p>
                </div>
                <p x-show="way.mode !== 'specific' && way.items.length > 0" aria-live="polite" class="mt-2 text-xs text-slate-500" x-text="way.items.length + ' accepted item' + (way.items.length === 1 ? '' : 's') + ' selected. The applicant needs ' + way.required_count + '.'"></p>
                <button type="button" @click="addItem(way)" :disabled="way.items.length >= 50" class="admin-secondary mt-3">Enter an item not in the directory</button>
                <p class="mt-1 text-xs text-slate-500">Use custom items for things outside the directory, such as a photo.</p>
            </div>

            <template x-for="(item, itemIndex) in way.items" :key="item.key">
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0" x-show="item.type !== 'custom'">
                            <p class="break-words text-sm font-medium" x-text="itemName(item)"></p>
                            <p class="mt-1 text-xs text-slate-500" x-text="item.type === 'government_id' ? 'Government ID / Permit' : 'Document'"></p>
                        </div>
                        <div x-show="item.type === 'custom'" class="min-w-0 flex-1 space-y-3">
                            <label class="block text-sm font-medium">Custom requirement
                                <input x-model="item.custom_name" maxlength="255" placeholder="e.g. 2x2 Picture" class="admin-input mt-1">
                            </label>

                        </div>
                        <button type="button" class="text-xs text-red-700" @click="removeItem(way, itemIndex)" :aria-label="'Remove ' + itemName(item)">Remove</button>
                    </div>
                    <button type="button" @click="item.details = !item.details" :aria-expanded="item.details" class="mt-3 text-xs font-medium text-slate-600">Optional details ▾</button>
                    <p x-show="!item.details && submission(item)" x-text="submission(item)" class="mt-2 text-xs text-slate-500"></p>
                    <p x-show="!item.details && item.instructions" x-text="item.instructions" class="mt-1 whitespace-pre-line text-xs text-slate-500"></p>
                    <div x-show="item.details" class="mt-3 space-y-3 border-t border-slate-100 pt-3">
                            <label x-show="item.type === 'custom'" class="block text-sm font-medium">Quantity (optional)
                                <input type="number" min="1" max="65535" step="1" x-model="item.quantity" class="admin-input mt-1">
                            </label>
                        <label class="block text-sm font-medium">Submission format
                            <select x-model="item.submission_format" class="admin-input mt-1">
                                @foreach(\App\Models\GovernmentIdRequirementItem::FORMATS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label x-show="item.submission_format === 'custom'" class="block text-sm font-medium">Describe the format
                            <input x-model="item.submission_format_custom" maxlength="255" class="admin-input mt-1">
                        </label>
                        <label x-show="['original', 'photocopy', 'original_photocopy', 'certified_true_copy', 'custom'].includes(item.submission_format)" class="block text-sm font-medium">
                            <span x-text="item.submission_format === 'original_photocopy' ? 'Number of photocopies (optional)' : 'Number of copies (optional)'"></span>
                            <input type="number" min="1" max="65535" step="1" x-model="item.copies" class="admin-input mt-1">
                        </label>
                        <label class="block text-sm font-medium">Additional instructions
                            <textarea x-model="item.instructions" rows="2" maxlength="10000" class="admin-input mt-1"></textarea>
                            <span class="mt-1 block text-xs font-normal text-slate-500">Use this for special instructions that the fields above cannot describe.</span>
                        </label>
                    </div>
                </div>
            </template>
            <div x-show="way.items.length > 0">
                <button type="button" @click="way.qualificationOpen = !way.qualificationOpen" :aria-expanded="way.qualificationOpen" class="text-sm font-medium text-slate-600">Advanced qualifications ▾</button>
                <div x-show="way.qualificationOpen" class="mt-3 space-y-3">
                    <p class="text-xs text-slate-500">Use this only if the chosen ID or document must meet an additional condition.</p>
                    <label class="block text-sm font-medium">Additional condition
                        <select :value="way.qualification_type" @change="if (convertLegacy()) way.qualification_type = $event.target.value; $event.target.value = way.qualification_type" class="admin-input mt-1">
                            @foreach(\App\Models\GovernmentIdRequirementWay::QUALIFICATIONS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label x-show="Number(way.required_count) > 1 && way.qualification_type !== 'none'" class="block text-sm font-medium">Which selected items must meet it?
                        <select x-model="way.qualification_scope" class="admin-input mt-1">
                            <option value="every">Every selected item</option>
                            <option value="at_least_one">At least one selected item</option>
                        </select>
                    </label>
                    <label x-show="way.qualification_type === 'custom'" class="block text-sm font-medium">Describe the qualification
                        <textarea x-model="way.qualification_custom" maxlength="1000" rows="2" class="admin-input mt-1"></textarea>
                    </label>
                </div>
                <p x-show="!way.qualificationOpen && qualification(way)" x-text="qualification(way)" class="mt-1 text-xs text-slate-500"></p>
            </div>
        </div>
    </template>

    <div class="border-t border-slate-200 pt-3">
        <p class="text-sm text-slate-600">Another way to meet this same requirement, such as 1 primary ID OR 2 supporting documents.</p>
        <button type="button" class="admin-secondary mt-3" @click="addWay()" :disabled="editing.ways.length >= 10 || editing.ways.some(way => !way.items.length)">+ Add OR alternative</button>
    </div>
    <p class="text-xs text-slate-500">Done editing updates the draft. Use the checklist action below to keep your changes.</p>
    <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-4">
        <button type="button" class="admin-secondary" @click="cancelRequirement()">Cancel card changes</button>
        <button type="button" class="admin-primary" @click="finishRequirement()">Done editing</button>
    </div>
</section>
