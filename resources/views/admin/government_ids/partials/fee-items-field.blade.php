@php
    $existingFees = old('fees', collect($selectedFees ?? [])->map(fn ($fee) => [
        ...$fee->only(['id', 'label', 'type', 'amount_min', 'amount_max', 'currency', 'is_optional', 'notes']),
        'options' => $fee->options->toArray(),
    ])->values()->all());
@endphp
<input type="hidden" name="fees_present" value="1">
<div x-data="governmentIdFeeEditor(@js($existingFees))" class="space-y-4" aria-label="Fee editor">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h3 class="font-semibold">Application Fees / Costs</h3><p class="text-xs text-slate-500">Add each fee or charge separately. Options within a fee mean choose one.</p></div>
        <button type="button" class="admin-secondary" @click="addFee()">+ Add Fee</button>
    </div>
    <p x-show="!fees.length" class="text-sm text-slate-500">No fees added yet.</p>
    <template x-for="(fee, index) in fees" :key="fee.key">
        <section class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4" :aria-label="'Fee ' + (index + 1)">
            <div class="flex items-center justify-between gap-3">
                <h4 class="font-semibold" x-text="'Fee ' + (index + 1)"></h4>
                <button type="button" class="text-sm text-red-700" @click="removeFee(index)">Remove fee</button>
            </div>
            <input type="hidden" :name="`fees[${index}][id]`" :value="fee.id || ''">
            <input type="hidden" :name="`fees[${index}][currency]`" :value="fee.currency">
            <label class="block text-sm font-medium">Fee Name
                <input :name="`fees[${index}][label]`" x-model="fee.label" maxlength="255" class="admin-input mt-1" placeholder="e.g. Service Fee">
            </label>
            <label class="block text-sm font-medium">Fee Type
                <select :name="`fees[${index}][type]`" x-model="fee.type" class="admin-input mt-1">
                    <option value="fixed">Fixed Amount</option><option value="range">Amount Range</option><option value="free">Free</option><option value="varies">Varies</option>
                    <option value="options" :disabled="fee.type !== 'options'">Multiple options — choose one</option>
                </select>
            </label>
            <div x-show="fee.type !== 'options'" class="grid gap-3 sm:grid-cols-2">
                <label x-show="fee.type === 'fixed' || fee.type === 'range'" class="text-sm">Amount / minimum (₱)
                    <input type="number" min="0" step="0.01" :name="`fees[${index}][amount_min]`" x-model="fee.amount_min" class="admin-input mt-1">
                </label>
                <label x-show="fee.type === 'range'" class="text-sm">Maximum amount (₱)
                    <input type="number" min="0" step="0.01" :name="`fees[${index}][amount_max]`" x-model="fee.amount_max" class="admin-input mt-1">
                </label>
            </div>
            <button x-show="fee.type !== 'options'" type="button" class="admin-secondary" @click="enableOptions(fee)">Add fee options</button>
            <template x-if="fee.type === 'options'">
                <div class="space-y-4">
                    <p class="font-medium">Choose one option — these amounts are not added together.</p>
                    <p x-show="fee.options.length < 2" class="text-sm text-amber-800">Add at least two options, or change Fee Type back to a simple fee.</p>
                    <template x-for="(option, optionIndex) in fee.options" :key="option.key">
                        <section class="space-y-3 rounded-lg border border-slate-200 bg-white p-4" :aria-label="'Option ' + (optionIndex + 1)">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h5 class="font-medium" x-text="'Option ' + (optionIndex + 1)"></h5>
                                <div class="flex flex-wrap gap-3 text-sm">
                                    <button type="button" :disabled="optionIndex === 0" @click="moveOption(fee, optionIndex, -1)" class="disabled:opacity-30">Move up</button>
                                    <button type="button" :disabled="optionIndex === fee.options.length - 1" @click="moveOption(fee, optionIndex, 1)" class="disabled:opacity-30">Move down</button>
                                    <button type="button" class="text-red-700" @click="removeOption(fee, optionIndex)">Remove option</button>
                                </div>
                            </div>
                            <input type="hidden" :name="`fees[${index}][options][${optionIndex}][id]`" :value="option.id || ''">
                            <input type="hidden" :name="`fees[${index}][options][${optionIndex}][currency]`" :value="option.currency">
                            <label class="block text-sm">Option Name
                                <input :name="`fees[${index}][options][${optionIndex}][label]`" x-model="option.label" maxlength="255" class="admin-input mt-1">
                            </label>
                            <label class="block text-sm">Fee Type
                                <select :name="`fees[${index}][options][${optionIndex}][type]`" x-model="option.type" class="admin-input mt-1">
                                    <option value="fixed">Fixed Amount</option><option value="range">Amount Range</option><option value="free">Free</option><option value="varies">Varies</option>
                                </select>
                            </label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label x-show="option.type === 'fixed' || option.type === 'range'" class="text-sm">Amount / minimum (₱)
                                    <input type="number" min="0" step="0.01" :name="`fees[${index}][options][${optionIndex}][amount_min]`" x-model="option.amount_min" class="admin-input mt-1">
                                </label>
                                <label x-show="option.type === 'range'" class="text-sm">Maximum amount (₱)
                                    <input type="number" min="0" step="0.01" :name="`fees[${index}][options][${optionIndex}][amount_max]`" x-model="option.amount_max" class="admin-input mt-1">
                                </label>
                            </div>
                            <label class="block text-sm">Description
                                <textarea :name="`fees[${index}][options][${optionIndex}][description]`" x-model="option.description" maxlength="10000" rows="2" class="admin-input mt-1"></textarea>
                            </label>
                            <label class="block text-sm">Notes
                                <textarea :name="`fees[${index}][options][${optionIndex}][notes]`" x-model="option.notes" maxlength="10000" rows="2" class="admin-input mt-1"></textarea>
                            </label>
                        </section>
                    </template>
                    <button type="button" class="admin-secondary" :disabled="fee.options.length >= 50" @click="addOption(fee)">+ Add another option</button>
                </div>
            </template>
            <label class="block text-sm">Notes
                <textarea :name="`fees[${index}][notes]`" x-model="fee.notes" rows="2" class="admin-input mt-1" placeholder="Optional conditions or details about this fee."></textarea>
            </label>
            <input type="hidden" :name="`fees[${index}][is_optional]`" value="0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" value="1" :name="`fees[${index}][is_optional]`" x-model="fee.is_optional">This fee is optional</label>
        </section>
    </template>
</div>
