<section class="space-y-5" aria-label="Requirement editor">
    <div x-show="editing.choosing" class="space-y-3">
        <p class="mb-4 text-sm text-slate-500">Choose the option that matches the agency’s requirement.</p>
        <button type="button" @click="chooseRequirementType('specific')" class="block w-full rounded-xl border border-slate-300 bg-white p-5 text-left transition-colors hover:border-blue-700 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
            <span class="flex items-center justify-between gap-3 font-semibold text-slate-900"><span>One specific item</span><span aria-hidden="true" class="text-blue-800">→</span></span>
            <span class="mt-2 block text-sm text-slate-600">The applicant must provide this particular item.</span>
            <span class="mt-2 block text-xs text-slate-500">Example: PSA Birth Certificate or a 2×2 photo.</span>
        </button>
        <button type="button" @click="chooseRequirementType('accepted')" class="block w-full rounded-xl border border-slate-300 bg-white p-5 text-left transition-colors hover:border-blue-700 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
            <span class="flex items-center justify-between gap-3 font-semibold text-slate-900"><span>Choose from accepted items</span><span aria-hidden="true" class="text-blue-800">→</span></span>
            <span class="mt-2 block text-sm text-slate-600">The applicant chooses how to meet this requirement from your accepted list.</span>
            <span class="mt-2 block text-xs text-slate-500">Example: Any 1 accepted ID or any 2 supporting documents.</span>
        </button>
        <button type="button" @click="chooseRequirementType('conditional')" class="block w-full rounded-xl border border-slate-300 bg-white p-5 text-left transition-colors hover:border-blue-700 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
            <span class="flex items-center justify-between gap-3 font-semibold text-slate-900"><span>Only in certain situations</span><span aria-hidden="true" class="text-blue-800">→</span></span>
            <span class="mt-2 block text-sm text-slate-600">Only applicants in the situation you specify need to provide this.</span>
            <span class="mt-2 block text-xs text-slate-500">Example: Marriage Certificate when using a spouse’s surname.</span>
        </button>
        <div class="flex flex-wrap justify-between gap-3 border-t border-slate-200 pt-4">
            <button type="button" class="admin-secondary" @click="cancelRequirement()">← Back to checklist</button>
            <button type="button" x-show="editing.visited" class="admin-primary" @click="resumeRequirement()">Resume editing</button>
        </div>
    </div>

    <div x-show="!editing.choosing" class="space-y-5">
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

    <label x-show="editing.title || editing.ways.length > 1 || editing.ways.some(way => way.mode === 'accepted')" class="block text-sm font-medium">
        Requirement name
        <input x-model="editing.title" maxlength="255" placeholder="e.g. Valid ID or Proof of Identity" class="admin-input mt-2">
    </label>

    <div x-show="editing.ways.length > 1" class="rounded-lg border border-slate-200 p-4">
        <p class="text-sm font-medium text-slate-800">The applicant can use any one of these alternatives.</p>
        <template x-for="(way, index) in editing.ways" :key="way.key">
            <p class="mt-2 text-sm text-slate-600" x-text="(index > 0 ? 'OR — ' : '') + waySummary(way)"></p>
        </template>
        <button type="button" class="mt-3 text-sm font-medium text-[#012877]" @click="editing.advancedOpen = !editing.advancedOpen" :aria-expanded="editing.advancedOpen" x-text="editing.advancedOpen ? 'Hide alternative editors' : 'Edit these alternatives'"></button>
    </div>
    <p x-show="editing.conditional && editing.condition_type !== 'always'" class="text-sm text-slate-600" x-text="'Only if: ' + conditionLabel(editing)"></p>

    <template x-for="(way, wayIndex) in editing.ways" :key="way.key">
        <div x-show="editing.ways.length === 1 || editing.advancedOpen" class="space-y-4">
            <div x-show="editing.ways.length > 1" class="flex items-center justify-between border-t border-slate-200 pt-4">
                <p class="text-sm font-semibold" x-text="wayIndex === 0 ? 'Alternative 1' : 'OR — Alternative ' + (wayIndex + 1)"></p>
                <button type="button" class="text-xs text-red-700" @click="removeWay(wayIndex)">Remove alternative</button>
            </div>

            <fieldset x-show="editing.conditional && editing.ways.length === 1" class="space-y-2">
                <legend class="mb-2 text-sm font-medium">What must they provide?</legend>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" :name="'requirement-selection-' + way.key" :checked="way.mode === 'specific'" @change="useSpecificItem(way)" :disabled="way.items.length > 1 || Number(way.required_count) !== 1">
                    One specific item
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" :name="'requirement-selection-' + way.key" :checked="way.mode === 'accepted'" @change="allowAcceptedItems(way)">
                    Choose from accepted items
                </label>
                <p x-show="way.items.length > 1 || Number(way.required_count) !== 1" class="text-xs text-slate-500">To use one specific item, keep one item and set the number needed to 1.</p>
            </fieldset>

            <label x-show="way.mode === 'accepted'" class="block text-sm font-medium">How many are needed?
                <input type="number" min="1" max="50" step="1" x-model="way.required_count" class="admin-input mt-2">
                <span class="mt-1 block text-xs font-normal text-slate-500">Choose how many accepted items the applicant must provide.</span>
            </label>

            <div data-requirement-search x-show="way.mode === 'accepted' || way.items.length === 0">
                <label class="block text-sm font-medium">
                    <span x-text="way.mode === 'accepted' ? 'Accepted items' : 'What does the applicant need?'"></span>
                    <input type="search" x-model="way.search" placeholder="Search IDs, permits, or documents…" autocomplete="off" class="admin-input mt-2">
                </label>
                <p class="mt-1 text-xs text-slate-500" x-text="way.mode === 'accepted' ? 'Use + to add accepted items. Your search stays open so you can keep adding.' : 'Start typing a name, such as Student Permit or Birth Certificate.'"></p>
                <div x-show="way.search.trim()" class="mt-2 max-h-44 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1">
                    <template x-for="option in matches(way)" :key="option.type + ':' + option.id">
                        <div class="flex items-center justify-between gap-3 rounded px-3 py-2 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="break-words text-slate-800" x-text="option.name"></p>
                                <p class="mt-1 text-xs text-slate-500" x-text="option.type === 'government_id' ? 'Government ID / Permit' : 'Document'"></p>
                            </div>
                            <button type="button" @click="addItem(way, option, $event)"
                                :disabled="isSelected(way, option) || way.items.length >= 50"
                                :aria-label="(isSelected(way, option) ? 'Added ' : (way.mode === 'accepted' ? 'Add ' : 'Select ')) + option.name"
                                class="flex min-h-10 min-w-10 shrink-0 items-center justify-center rounded-md border border-slate-200 px-2 text-[#012877] hover:bg-blue-50 disabled:cursor-default disabled:border-transparent disabled:text-slate-500 disabled:hover:bg-transparent">
                                <span x-show="!isSelected(way, option)" class="text-sm font-medium" x-text="way.mode === 'accepted' ? '+ Add' : 'Select'"></span>
                                <span x-show="isSelected(way, option)" class="text-xs">✓ Added</span>
                            </button>
                        </div>
                    </template>
                    <p x-show="matches(way).length === 0" class="p-3 text-xs text-slate-500">No matches. Try another name, or add a custom item below.</p>
                </div>
                <p x-show="way.mode === 'accepted' && way.items.length > 0" aria-live="polite" class="mt-2 text-xs text-slate-500" x-text="way.items.length + ' accepted item' + (way.items.length === 1 ? '' : 's') + ' selected. The applicant needs ' + way.required_count + '.'"></p>
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
                            <label class="block text-sm font-medium">Quantity (optional)
                                <input type="number" min="1" max="65535" step="1" x-model="item.quantity" class="admin-input mt-1">
                            </label>
                        </div>
                        <button type="button" class="text-xs text-red-700" @click="removeItem(way, itemIndex)" :aria-label="'Remove ' + itemName(item)">Remove</button>
                    </div>
                    <button type="button" @click="item.details = !item.details" :aria-expanded="item.details" class="mt-3 text-xs font-medium text-slate-600">Optional details ▾</button>
                    <p x-show="!item.details && submission(item)" x-text="submission(item)" class="mt-2 text-xs text-slate-500"></p>
                    <p x-show="!item.details && item.instructions" x-text="item.instructions" class="mt-1 whitespace-pre-line text-xs text-slate-500"></p>
                    <div x-show="item.details" class="mt-3 space-y-3 border-t border-slate-100 pt-3">
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
            <details x-show="way.items.length > 0 && (!editing.conditional || editing.ways.length > 1)" class="text-sm">
                <summary class="cursor-pointer text-slate-500">Change selection type</summary>
                <div class="mt-3">
                    <button type="button" x-show="way.mode === 'specific'" @click="allowAcceptedItems(way)" class="admin-secondary">Use accepted choices instead</button>
                    <button type="button" x-show="way.mode === 'accepted'" :disabled="way.items.length > 1 || Number(way.required_count) !== 1" @click="useSpecificItem(way)" class="admin-secondary">Use one specific item instead</button>
                    <p x-show="way.mode === 'accepted' && (way.items.length > 1 || Number(way.required_count) !== 1)" class="mt-2 text-xs text-slate-500">Keep one item and set the number needed to 1 before switching.</p>
                </div>
            </details>

            <div x-show="way.items.length > 0">
                <button type="button" @click="way.qualificationOpen = !way.qualificationOpen" :aria-expanded="way.qualificationOpen" class="text-sm font-medium text-slate-600">Extra conditions ▾</button>
                <div x-show="way.qualificationOpen" class="mt-3 space-y-3">
                    <p class="text-xs text-slate-500">Use this only if the chosen ID or document must meet an additional condition.</p>
                    <label class="block text-sm font-medium">Additional condition
                        <select x-model="way.qualification_type" class="admin-input mt-1">
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

    <details x-show="editing.ways.some(way => way.items.length > 0)" class="border-t border-slate-200 pt-3">
        <summary class="cursor-pointer text-sm font-medium text-slate-600">More options</summary>
        <div class="mt-4">
            <p class="text-sm font-medium text-slate-800">Only required in certain situations</p>
            <label class="mt-3 block text-sm"><input type="checkbox" x-model="editing.conditional" class="mr-2">Yes, only in a specific situation</label>
            <p x-show="editing.conditional" class="mt-2 text-xs text-slate-500">Choose the situation at the top of this form.</p>

        </div>
        <details class="mt-5 border-t border-slate-100 pt-3">
            <summary class="cursor-pointer text-xs font-medium text-slate-500">Advanced: different ways to meet this requirement</summary>
            <p class="mt-3 text-xs leading-5 text-slate-500">Use this only for instructions such as “1 primary ID OR 2 secondary documents.” For another required document, add a separate requirement to the checklist.</p>
            <button type="button" class="mt-3 text-sm font-medium text-[#012877]" @click="addWay()" :disabled="editing.ways.length >= 10 || editing.ways.some(way => !way.items.length)">+ Add an alternative</button>
        </details>
    </details>

    <p class="text-xs text-slate-500" x-text="deferred ? 'This adds the requirement to your checklist. Apply the checklist, then Create ID to save everything.' : 'This adds the requirement to your list. Save the checklist when you’re finished.'"></p>
    <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-4">
        <button type="button" class="admin-secondary mr-auto" @click="chooseAgain()">← Back to choices</button>
        <button type="button" class="admin-secondary" @click="cancelRequirement()">Cancel</button>
        <button type="button" class="admin-primary" :disabled="editing.ways.some(way => !way.items.length)" @click="finishRequirement()" x-text="editingIndex === null ? 'Add requirement' : 'Update requirement'"></button>
    </div>
    </div>
</section>
