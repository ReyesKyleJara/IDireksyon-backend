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
                        <p class="font-medium" x-text="way.items.length ? waySummary(way) : 'Select an item to complete this draft.'"></p>
                        <p x-show="Number(way.required_count) > way.items.length || Number(way.required_count) < 1" class="text-red-700">Incomplete draft: check the number of accepted items needed.</p>
                        <p x-show="qualification(way)" class="text-blue-800" x-text="qualification(way)"></p>
                        <ul class="list-disc space-y-2 pl-5">
                            <template x-for="item in way.items" :key="item.key">
                                <li class="break-words">
                                    <span x-text="itemName(item)"></span>
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
