<section class="space-y-4 rounded-xl border border-blue-200 bg-slate-50 p-4" aria-label="Requirement editor">
    <h3 class="font-semibold text-slate-900" x-text="editingIndex === null ? 'Add requirement' : 'Edit requirement'"></h3>

    <label x-show="editing.ways.length > 1 || editing.ways.some(way => way.mode === 'accepted')" class="block text-sm font-medium">
        Requirement name
        <input x-model="editing.title" maxlength="255" placeholder="e.g. Proof of Identity" class="admin-input mt-2">
    </label>

    <template x-for="(way, wayIndex) in editing.ways" :key="way.key">
        <div class="space-y-4">
            <div x-show="editing.ways.length > 1" class="flex items-center justify-between border-t border-slate-200 pt-4">
                <p class="text-sm font-semibold" x-text="wayIndex === 0 ? 'Way 1' : 'OR — Way ' + (wayIndex + 1)"></p>
                <button type="button" class="text-xs text-red-700" @click="editing.ways.splice(wayIndex, 1)">Remove this way</button>
            </div>

            <div>
                <p class="mb-2 text-sm font-medium">What does the applicant need?</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button type="button" @click="chooseMode(way, 'specific')" :disabled="way.items.length > 0" :aria-pressed="way.mode === 'specific'"
                        :class="way.mode === 'specific' ? 'border-blue-700 bg-blue-50 text-blue-900' : 'border-slate-200 bg-white text-slate-700'"
                        class="rounded-lg border p-3 text-left text-sm disabled:cursor-default">One specific item</button>
                    <button type="button" @click="chooseMode(way, 'accepted')" :disabled="way.items.length > 0" :aria-pressed="way.mode === 'accepted'"
                        :class="way.mode === 'accepted' ? 'border-blue-700 bg-blue-50 text-blue-900' : 'border-slate-200 bg-white text-slate-700'"
                        class="rounded-lg border p-3 text-left text-sm disabled:cursor-default">Choose from accepted items</button>
                </div>
                <button type="button" x-show="way.items.length" class="mt-2 text-xs text-slate-500"
                    @click="if (window.confirm('Clear these selected items and choose again?')) { way.items = []; way.mode = ''; way.required_count = 1; }">Change selection type</button>
            </div>

            <label x-show="way.mode === 'specific'" class="block text-sm font-medium">Requirement type
                <select x-model="way.type" @change="changeType(way)" class="admin-input mt-2">
                    <option value="document">Document</option>
                    <option value="government_id">Government ID</option>
                    <option value="custom">Other / Custom</option>
                </select>
            </label>

            <label x-show="way.mode === 'accepted'" class="block text-sm font-medium">How many are needed?
                <input type="number" min="1" max="50" step="1" x-model="way.required_count" class="admin-input mt-2">
                <span class="mt-1 block text-xs font-normal text-slate-500">Choose how many accepted items the applicant must provide.</span>
            </label>

            <div x-show="way.mode && (way.mode === 'accepted' || (way.type !== 'custom' && way.items.length === 0))">
                <label class="block text-sm font-medium">
                    <span x-text="way.mode === 'accepted' ? 'Accepted items' : 'Find the item'"></span>
                    <input type="search" x-model="way.search" placeholder="Search by name…" autocomplete="off" class="admin-input mt-2">
                </label>
                <p class="mt-1 text-xs text-slate-500" x-text="way.mode === 'accepted' ? 'Only selected IDs or documents will count for this requirement.' : 'Start typing to find a record in the directory.'"></p>
                <div x-show="way.search.trim()" class="mt-2 max-h-44 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1">
                    <template x-for="option in matches(way)" :key="option.type + ':' + option.id">
                        <button type="button" @click="addItem(way, option)" :disabled="way.items.length >= 50"
                            class="flex w-full items-center justify-between gap-3 rounded px-3 py-2 text-left text-sm hover:bg-blue-50">
                            <span x-text="option.name"></span>
                            <span class="shrink-0 text-xs text-slate-500" x-text="option.type === 'government_id' ? 'Government ID' : 'Document'"></span>
                        </button>
                    </template>
                    <p x-show="matches(way).length === 0" class="p-3 text-xs text-slate-500">No matches. Try another name.</p>
                </div>
            </div>

            <template x-for="(item, itemIndex) in way.items" :key="item.key">
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div x-show="item.type !== 'custom'">
                            <p class="text-sm font-medium" x-text="itemName(item)"></p>
                            <p class="mt-1 text-xs text-slate-500" x-text="item.type === 'government_id' ? 'Government ID' : 'Document'"></p>
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
                    <button type="button" @click="item.details = !item.details" :aria-expanded="item.details" class="mt-3 text-xs font-medium text-slate-600">Submission details ▾</button>
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
            <button type="button" x-show="way.mode === 'specific' && way.type === 'custom' && way.items.length === 0"
                @click="addItem(way)" class="text-sm text-[#012877]">Enter custom requirement</button>

            <div x-show="way.mode">
                <button type="button" @click="way.qualificationOpen = !way.qualificationOpen" :aria-expanded="way.qualificationOpen" class="text-sm font-medium text-slate-600">Selected item must… ▾</button>
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

    <details class="border-t border-slate-200 pt-3">
        <summary class="cursor-pointer text-sm font-medium text-slate-600">Does this only apply in a specific situation?</summary>
        <label class="mt-3 block text-sm"><input type="checkbox" x-model="editing.conditional" class="mr-2">Yes, only in a specific situation</label>
        <div x-show="editing.conditional" class="mt-3 space-y-3">
            <label class="block text-sm font-medium">Applies when
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
    </details>

    <details class="border-t border-slate-200 pt-3">
        <summary class="cursor-pointer text-xs text-slate-500">More options</summary>
        <button type="button" class="mt-3 text-sm font-medium text-[#012877]" @click="addWay()" :disabled="editing.ways.length >= 10">+ Add another way to satisfy this requirement</button>
    </details>

    <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
        <button type="button" class="admin-secondary" @click="cancelRequirement()">Cancel requirement</button>
        <button type="button" class="admin-primary" @click="finishRequirement()">Done</button>
    </div>
</section>
