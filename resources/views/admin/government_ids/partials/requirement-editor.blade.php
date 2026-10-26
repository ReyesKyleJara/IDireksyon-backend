<section class="space-y-3" aria-label="Requirement editor">
    <h4 class="font-semibold" x-text="editingIndex === null ? 'New Requirement' : 'Edit Requirement'"></h4>

    <label x-show="editing.ways.length > 1 || editing.ways[0].mode !== 'specific'" class="block text-sm font-medium">
        Requirement name
        <input x-model="editing.title" maxlength="255" placeholder="e.g. Supporting Documents" class="admin-input mt-1">
    </label>

    <template x-for="(way, wayIndex) in editing.ways" :key="way.key">
        <div class="space-y-3">
            <div x-show="wayIndex > 0" class="flex items-center gap-3 pt-1" aria-label="OR alternative">
                <span class="h-px flex-1 bg-slate-200"></span>
                <span class="text-xs font-semibold text-slate-500">OR</span>
                <span class="h-px flex-1 bg-slate-200"></span>
            </div>
            <div x-show="editing.ways.length > 1" class="flex items-center justify-between text-xs text-slate-500">
                <span x-text="'Alternative ' + (wayIndex + 1)"></span>
                <button type="button" class="text-red-700" @click="removeWay(wayIndex)">Remove alternative</button>
            </div>

            <div class="flex flex-col gap-2">
                <div data-requirement-search class="order-1 space-y-2">
                    <label x-show="way.mode === 'accepted'" class="block text-sm font-medium">How many are required?
                        <input type="number" min="1" max="50" step="1" :value="way.required_count" @change="setCount(way, $event.target.value); $event.target.value = way.required_count" class="admin-input mt-1 w-24">
                    </label>
                    <label class="block text-sm font-medium">
                        <span x-text="way.mode === 'specific' ? 'What does the applicant need?' : 'Accepted items'"></span>
                        <input type="search" :value="way.mode === 'specific' && way.items.length ? (way.items[0].type === 'custom' ? way.items[0].custom_name : itemName(way.items[0])) : way.search" @input="way.mode === 'specific' ? singleItemText(way, $event.target.value) : way.search = $event.target.value" :placeholder="way.mode === 'specific' ? 'Type an item or search the directory…' : 'Search IDs or documents…'" maxlength="255" autocomplete="off" class="admin-input mt-1">
                    </label>
                <div x-show="way.search.trim() && (way.mode !== 'specific' || matches(way).length)" class="mt-2 max-h-32 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1">
                    <template x-for="option in matches(way)" :key="option.type + ':' + option.id">
                        <div class="flex items-center justify-between gap-3 rounded px-2 py-1 text-sm">
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
                    <p x-show="matches(way).length === 0" class="p-3 text-xs text-slate-500"><span x-text="way.mode === 'specific' ? 'No directory matches. Your text will be saved as a custom item.' : 'No matches. Try another name, or add a custom item below.'"></span></p>
                </div>

                </div>
                <label class="order-2 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">Requirement type
                    <select class="admin-input w-auto py-1 text-xs" :value="way.mode" @change="selection(way, $event.target.value); $event.target.value = way.mode">
                        <option value="specific" :disabled="way.items.length > 1">One specific item</option>
                        <option value="accepted">Choose from accepted items</option>
                        <option value="all">All listed items</option>
                    </select>
                </label>
            </div>

            <div x-show="wayIndex === 0" class="space-y-2">
                <label class="block text-sm"><input type="checkbox" x-model="editing.conditional" class="mr-2">Only required in a particular situation</label>
                <div x-show="editing.conditional" class="space-y-2 rounded-lg bg-slate-50 p-3">
                    <label class="block text-sm font-medium">When is it required?
                        <select x-model="editing.condition_type" class="admin-input mt-1">
                            <option value="always">Select a situation</option>
                            @foreach(\App\Models\GovernmentIdRequirementGroup::CONDITIONS as $key => $label)
                                @if($key !== 'always')
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </label>
                    <label x-show="editing.condition_type === 'custom'" class="block text-sm font-medium">Describe the situation
                        <textarea x-model="editing.condition_custom" maxlength="1000" rows="2" class="admin-input mt-1"></textarea>
                    </label>
                </div>
            </div>

            <div x-show="way.mode !== 'specific'" class="space-y-2">
                <button type="button" class="text-sm font-medium text-slate-600" :aria-expanded="way.commonOpen" @click="toggleCommonDetails(way)">Set common details ▾</button>
                <span x-show="commonDetails(way).mixed" class="ml-2 text-xs text-slate-500">Mixed values</span>
                <template x-if="way.commonOpen && way.commonDraft">
                    <section class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3" aria-label="Common details for accepted items">
                        <h5 class="text-sm font-semibold">Common details for accepted items</h5>
                        <p class="text-xs text-slate-500">Apply replaces these details for every accepted item in this alternative. Individual overrides can be edited afterward.</p>
                        <template x-if="way.commonDraft">
                            <div x-data="{ get item() { return way.commonDraft; } }">
                                @include('admin.government_ids.partials.requirement-item-details')
                            </div>
                        </template>
                        <button type="button" class="admin-secondary" :disabled="!way.items.length" @click="applyCommonDetails(way)">Apply to all accepted items</button>
                    </section>
                </template>
            </div>

            <div x-show="way.mode !== 'specific'" class="space-y-2">
                <template x-for="(item, itemIndex) in way.items" :key="item.key">
                    <div class="rounded-lg border border-slate-200 px-3 py-2">
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <input x-show="item.type === 'custom'" x-model="item.custom_name" maxlength="255" aria-label="Custom accepted item" placeholder="Custom item name" class="admin-input min-w-0 w-full py-1 text-sm">
                                <span x-show="item.type !== 'custom'" class="break-words text-sm" x-text="itemName(item)"></span>
                                <p x-show="!item.details && itemDetailsSummary(item)" x-text="itemDetailsSummary(item)" class="truncate text-xs text-slate-500"></p>
                            </div>
                            <div class="relative shrink-0" @click.outside="if (way.itemMenu === item.key) way.itemMenu = null" @keydown.escape.stop="way.itemMenu = null">
                                <button type="button" class="min-h-9 min-w-9 rounded text-xl hover:bg-slate-100" :aria-label="'Actions for ' + itemName(item)" :aria-expanded="way.itemMenu === item.key" @click="way.itemMenu = way.itemMenu === item.key ? null : item.key">⋮</button>
                                <div x-cloak x-show="way.itemMenu === item.key" class="absolute right-0 z-20 w-36 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                                    <button type="button" class="block w-full rounded px-3 py-2 text-left text-sm hover:bg-slate-50" @click="editItemDetails(way, item)">Edit details</button>
                                    <button type="button" class="block w-full rounded px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50" @click="removeItem(way, itemIndex)" :aria-label="'Remove ' + itemName(item)">Remove</button>
                                </div>
                            </div>
                        </div>
                        <template x-if="item.details">
                            <div class="mt-3 space-y-3 border-t border-slate-100 pt-3" :aria-label="'Details for ' + itemName(item)">
                                @include('admin.government_ids.partials.requirement-item-details')
                                <button type="button" class="text-sm font-medium text-slate-600" @click="item.details = false">Close details</button>
                            </div>
                        </template>
                    </div>
                </template>
                <button type="button" class="text-sm font-medium text-[#012877]" @click="addItem(way)" :disabled="way.items.length >= 50">+ Add accepted item</button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <button x-show="wayIndex === editing.ways.length - 1" type="button" class="text-sm font-medium text-[#012877]" title="Add OR alternative" @click="addWay()" :disabled="editing.ways.length >= 10 || editing.ways.some(way => !way.items.length)">+ Add alternative</button>
                <button type="button" class="ml-auto text-sm font-medium text-slate-600" @click="way.optionsOpen = !way.optionsOpen" :aria-expanded="way.optionsOpen">More options ▾</button>
            </div>
            <div x-show="way.optionsOpen" class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                <label x-show="wayIndex === 0 && editing.ways.length === 1 && way.mode === 'specific'" class="block text-sm font-medium">Requirement name (optional)
                    <input x-model="editing.title" maxlength="255" class="admin-input mt-1">
                </label>
                <p x-show="editing.legacy" class="text-xs text-slate-500">Legacy storage is preserved. Advanced alternatives require explicit conversion.</p>
                <h5 x-show="way.mode === 'specific'" class="text-sm font-semibold">Optional details</h5>
                <p x-show="!way.items.length" class="text-xs text-slate-500">Enter an item above to add its details.</p>
                <template x-for="item in (way.mode === 'specific' ? way.items : [])" :key="item.key">
                    <div x-show="way.mode === 'specific'" class="space-y-2">
                        <p x-show="way.mode !== 'specific'" class="text-sm font-medium" x-text="itemName(item)"></p>
                        @include('admin.government_ids.partials.requirement-item-details')
                    </div>
                </template>
            <div x-show="way.items.length > 0">
                <button type="button" @click="way.qualificationOpen = !way.qualificationOpen" :aria-expanded="way.qualificationOpen" class="text-sm font-medium text-slate-600">Advanced qualifications ▾</button>
                <div x-show="way.qualificationOpen" class="mt-2 space-y-3">
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
        </div>
    </template>

    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-3">
        <button type="button" class="admin-secondary" @click="cancelRequirement()">Cancel</button>
        <button type="button" class="admin-primary" @click="finishRequirement()">Done</button>
    </div>
</section>
