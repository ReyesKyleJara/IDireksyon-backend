@if($wizardMode ?? false)
<aside class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 p-4" aria-label="Requirements draft preview">
    <header class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="font-semibold">Requirements draft preview</h3>
        <span class="text-xs text-slate-500" x-text="previewGroups.length + (previewGroups.length === 1 ? ' requirement' : ' requirements')"></span>
    </header>
    <p class="mb-3 text-xs leading-5 text-slate-500">Live draft preview. Continue saves this step.</p>
    <p x-show="!previewGroups.length" class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500">No requirements added yet.</p>
    <div class="space-y-3">
        <template x-for="group in previewGroups" :key="group.key">
            <article class="min-w-0 rounded-xl border border-slate-200 bg-white p-4">
                <header class="flex items-start justify-between gap-3">
                    <h4 class="min-w-0 break-words text-sm font-semibold leading-5 text-slate-900" x-text="requirementName(group)"></h4>
                    <span class="shrink-0 rounded-full px-2 py-1 text-[11px] font-medium leading-none"
                        :class="group.condition_type === 'always' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-800'"
                        x-text="group.condition_type === 'always' ? 'Required' : 'Conditional'"></span>
                </header>
                <p x-show="group.condition_type !== 'always'" class="mt-2 rounded-lg bg-amber-50/60 px-3 py-2 text-xs leading-5 text-slate-700"
                    x-text="'Only if: ' + (conditionLabel(group) || 'Describe the situation').replace(/^(only\s+)?if\s+/i, '')"></p>
                <template x-for="(way, index) in ways(group)" :key="way.key">
                    <div class="mt-2 space-y-2 text-sm">
                        <div x-show="index > 0" class="flex items-center gap-3 py-1" aria-label="OR alternative">
                            <span class="h-px flex-1 bg-slate-200"></span><span class="text-[11px] font-semibold text-slate-500">OR</span><span class="h-px flex-1 bg-slate-200"></span>
                        </div>
                        <p x-show="ways(group).length > 1 || !way.items.length || waySummary(way) !== requirementName(group)" class="font-medium text-slate-800"
                            x-text="way.items.length ? waySummary(way).replace('accepted items', (way.items.every(item => item.type === 'government_id') || /\bIDs\b/i.test(requirementName(group)) ? 'accepted IDs' : 'accepted items')) : 'Select an item to complete this draft.'"></p>
                        <p x-show="Number(way.required_count) > way.items.length || Number(way.required_count) < 1" class="text-xs text-red-700">Incomplete draft: check the number of accepted items needed.</p>
                        <p x-show="qualification(way)" class="rounded-lg bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-700" x-text="qualification(way)"></p>
                        <details :open="way.items.length <= 4" class="group/accepted">
                            <summary x-show="way.items.length > 4" class="cursor-pointer rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                <span class="group-open/accepted:hidden" x-text="'View ' + way.items.length + ' ' + (way.items.every(item => item.type === 'government_id') || /\bIDs\b/i.test(requirementName(group)) ? 'accepted IDs' : 'accepted items')"></span>
                                <span class="hidden group-open/accepted:inline" x-text="'Hide ' + (way.items.every(item => item.type === 'government_id') || /\bIDs\b/i.test(requirementName(group)) ? 'accepted IDs' : 'accepted items')"></span>
                            </summary>
                            <ul class="space-y-2" :class="way.items.length > 4 ? 'mt-3' : ''">
                                <template x-for="item in way.items" :key="item.key">
                                    <li class="min-w-0 break-words text-xs leading-5" :class="way.items.length > 1 ? 'rounded-lg bg-slate-50 px-3 py-2' : ''">
                                        <p x-show="way.items.length > 1" class="font-medium text-slate-800" x-text="itemName(item)"></p>
                                        <p x-show="item.quantity || submission(item)" class="text-slate-600"
                                            x-text="[item.quantity ? item.quantity + ' required' : '', submission(item).replace(/^Original \+ Photocopy · (\d+) photocop(y|ies)$/, 'Original + $1 photocop$2')].filter(Boolean).join(' · ')"></p>
                                        <p x-show="way.items.length <= 4 && item.instructions" class="whitespace-pre-line text-slate-600" x-text="item.instructions"></p>
                                    </li>
                                </template>
                            </ul>
                        </details>
                        {{-- Keep instructions visible even when a long accepted-item list is collapsed. --}}
                        <template x-if="way.items.length > 4">
                            <div class="space-y-2">
                                <template x-for="item in way.items.filter(item => item.instructions)" :key="item.key">
                                    <p class="whitespace-pre-line text-xs leading-5 text-slate-600"><span class="font-medium" x-text="itemName(item) + ': '"></span><span x-text="item.instructions"></span></p>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </article>
        </template>
    </div>
</aside>
@else
<aside class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 p-4" aria-label="Requirements draft preview">
    <h3 class="font-semibold">Requirements draft preview</h3>
    <p class="mt-2 text-sm text-slate-600">Unsaved wording preview. Conditions are shown as instructions; this does not determine anyone’s eligibility or readiness.</p>
    <p class="mt-2 text-sm font-medium" x-text="checklistLabel(draft)"></p>
    <p x-show="!previewGroups.length" class="mt-4 text-sm text-slate-500">No requirements added to this scenario yet.</p>
    <div class="mt-4 space-y-4">
        <template x-for="group in previewGroups" :key="group.key">
            <article class="rounded-lg border border-slate-200 bg-white p-4">
                <h4 class="break-words font-semibold" x-text="requirementName(group)"></h4>
                <p class="mt-1 text-sm text-blue-800" x-text="group.condition_type === 'always' ? 'Required' : 'Only if: ' + (conditionLabel(group) || 'Describe the situation')"></p>
                <template x-for="(way, index) in ways(group)" :key="way.key">
                    <div class="mt-3 space-y-2 text-sm">
                        <p x-show="index > 0" class="font-semibold">OR</p>
                        <p x-show="ways(group).length > 1 || !way.items.length || waySummary(way) !== requirementName(group)" class="font-medium" x-text="way.items.length ? waySummary(way) : 'Select an item to complete this draft.'"></p>
                        <p x-show="Number(way.required_count) > way.items.length || Number(way.required_count) < 1" class="text-red-700">Incomplete draft: check the number of accepted items needed.</p>
                        <p x-show="qualification(way)" class="text-blue-800" x-text="qualification(way)"></p>
                        <ul class="space-y-2" :class="way.items.length === 1 && Number(way.required_count) === 1 ? 'list-none' : 'list-disc pl-5'">
                            <template x-for="item in way.items" :key="item.key">
                                <li class="break-words">
                                    <span x-show="way.items.length !== 1 || Number(way.required_count) !== 1" x-text="itemName(item)"></span>
                                    <p x-show="item.quantity" x-text="'Quantity: ' + item.quantity"></p>
                                    <p x-show="submission(item)" x-text="submission(item)"></p>
                                    <p x-show="item.instructions" class="whitespace-pre-line" x-text="item.instructions"></p>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </article>
        </template>
    </div>
</aside>
@endif
