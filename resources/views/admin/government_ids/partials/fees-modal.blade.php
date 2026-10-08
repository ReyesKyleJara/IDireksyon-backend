@php
    $savedFees = collect($selectedFees ?? [])->map(fn ($fee) => $fee->only([
        'label', 'type', 'amount_min', 'amount_max', 'is_optional', 'notes',
    ]))->values()->all();
    $initialFees = session()->hasOldInput() ? old('fees', []) : $savedFees;
    $feeErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => $key === 'fees' || str_starts_with($key, 'fees.'))->flatten()->all();
@endphp

<section class="admin-panel p-6" aria-label="Fees" x-data="governmentIdFeeModal(@js($initialFees))">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Fees</h2>
            <p class="mt-1 text-sm text-slate-500">Application charges and optional costs.</p>
        </div>
        <button type="button" class="admin-secondary" @click="open($event)">Edit fees</button>
    </div>
    @if(count($feeErrors))
        <ul class="mb-4 list-disc pl-5 text-sm text-red-700" role="alert">
            @foreach($feeErrors as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif
    <p x-show="fees.length === 0" class="text-sm text-slate-500">Add fees when the costs are available.</p>
    <div class="space-y-3">
        <template x-for="fee in fees" :key="fee.key">
            <div class="rounded-lg border border-slate-200 p-4">
                <div class="flex flex-wrap justify-between gap-2 text-sm">
                    <p class="font-medium text-slate-900"><span x-text="fee.label || 'Fee'"></span><span x-show="fee.is_optional" class="ml-2 text-xs font-normal text-slate-500">Optional</span></p>
                    <p class="font-medium text-slate-700" x-text="amount(fee)"></p>
                </div>
                <p x-show="fee.notes" x-text="fee.notes" class="mt-1 whitespace-pre-line text-xs text-slate-500"></p>
            </div>
        </template>
    </div>
    <p x-show="changed" role="status" class="mt-3 text-xs text-blue-800">Fees updated in this form. Click Save Changes to save them.</p>

    {{-- Only applied values are submitted by the main ID form. --}}
    <template x-for="(fee, index) in fees" :key="fee.key">
        <div hidden>
            <input type="hidden" :name="'fees[' + index + '][label]'" :value="fee.label">
            <input type="hidden" :name="'fees[' + index + '][type]'" :value="fee.type">
            <input type="hidden" :name="'fees[' + index + '][amount_min]'" :value="fee.amount_min">
            <input type="hidden" :name="'fees[' + index + '][amount_max]'" :value="fee.amount_max">
            <input type="hidden" :name="'fees[' + index + '][is_optional]'" :value="fee.is_optional ? '1' : '0'">
            <input type="hidden" :name="'fees[' + index + '][notes]'" :value="fee.notes">
        </div>
    </template>

    <style>#fees-dialog::backdrop { background: rgb(15 23 42 / .5); }</style>
    <template x-teleport="body">
        <dialog id="fees-dialog" x-ref="dialog" aria-labelledby="fees-dialog-title" @cancel.prevent="cancel()"
            class="m-auto rounded-2xl border-0 bg-white p-0 shadow-xl"
            style="width: min(640px, calc(100vw - 2rem)); max-width: none; max-height: calc(100dvh - 2rem);">
            <div class="flex flex-col" style="max-height: calc(100dvh - 2rem);">
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-6 py-4">
                    <div>
                        <h2 id="fees-dialog-title" class="text-lg font-semibold text-slate-900">Edit fees</h2>
                        <p class="mt-1 text-sm text-slate-500">Add each charge separately.</p>
                    </div>
                    <button type="button" @click="cancel()" aria-label="Close fees" class="text-2xl text-slate-500">×</button>
                </header>
                <div class="min-h-0 space-y-4 overflow-y-auto px-6 py-5">
                    <p x-show="error" x-text="error" x-ref="error" tabindex="-1" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700"></p>
                    <template x-for="(fee, index) in draft" :key="fee.key">
                        <div class="space-y-4 rounded-xl border border-slate-200 p-4">
                            <div class="flex justify-between gap-3">
                                <h3 class="text-sm font-semibold" x-text="'Fee ' + (index + 1)"></h3>
                                <button type="button" @click="draft.splice(index, 1)" class="text-xs text-red-700">Remove</button>
                            </div>
                            <label class="block text-sm font-medium">Fee name
                                <input x-model="fee.label" maxlength="255" class="admin-input mt-1" placeholder="e.g. Application fee">
                            </label>
                            <label class="block text-sm font-medium">Fee type
                                <select x-model="fee.type" class="admin-input mt-1">
                                    <option value="fixed">Fixed amount</option>
                                    <option value="range">Amount range</option>
                                    <option value="free">Free</option>
                                    <option value="varies">Varies</option>
                                </select>
                            </label>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label x-show="fee.type === 'fixed' || fee.type === 'range'" class="block text-sm font-medium">
                                    <span x-text="fee.type === 'range' ? 'Minimum amount (₱)' : 'Amount (₱)'"></span>
                                    <input type="number" min="0" step="0.01" x-model="fee.amount_min" class="admin-input mt-1">
                                </label>
                                <label x-show="fee.type === 'range'" class="block text-sm font-medium">Maximum amount (₱)
                                    <input type="number" min="0" step="0.01" x-model="fee.amount_max" class="admin-input mt-1">
                                </label>
                            </div>
                            <label class="block text-sm font-medium">Notes (optional)
                                <textarea x-model="fee.notes" rows="2" class="admin-input mt-1"></textarea>
                            </label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="fee.is_optional" class="rounded border-slate-300">This fee is optional</label>
                        </div>
                    </template>
                    <button type="button" class="admin-secondary" @click="draft.push(normalize())">+ Add fee</button>
                </div>
                <footer class="shrink-0 border-t border-slate-200 px-6 py-4">
                    <p class="mb-3 text-xs text-slate-500">Apply fees, then click Save Changes on the page to save.</p>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="admin-secondary" @click="cancel()">Cancel</button>
                        <button type="button" class="admin-primary" @click="apply()">Apply fees</button>
                    </div>
                </footer>
            </div>
        </dialog>
    </template>
</section>


