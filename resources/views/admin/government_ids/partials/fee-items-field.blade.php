@php
    $existingFees = old('fees');

    if ($existingFees === null) {
        $existingFees = collect(
            $selectedFees ?? []
        )->map(function ($fee) {
            return [
                'label' => $fee->label,
                'type' => $fee->type,
                'amount_min' => $fee->amount_min,
                'amount_max' => $fee->amount_max,
                'is_optional' => $fee->is_optional,
                'notes' => $fee->notes,
            ];
        })->values()->all();
    }
@endphp


<div
    x-data="governmentIdFeeEditor(
        @js($existingFees)
    )"
    class="sm:col-span-2"
>

    <div class="mb-4 flex items-center justify-between gap-4">

        <div>

            <label class="block text-sm font-medium">
                Application Fees / Costs
            </label>

            <p class="mt-1 text-xs text-slate-500">
                Add each fee or charge separately.
            </p>

        </div>


        <button
            type="button"
            class="admin-secondary whitespace-nowrap"
            @click="addFee()"
        >
            + Add Fee
        </button>

    </div>


    <div class="space-y-4">

        <template
            x-for="(fee, index) in fees"
            :key="fee.key"
        >

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">

                <div class="mb-4 flex items-center justify-between">

                    <p class="text-sm font-semibold text-slate-900">
                        Fee
                        <span x-text="index + 1"></span>
                    </p>


                    <button
                        type="button"
                        class="text-sm font-medium text-red-600 hover:underline"
                        @click="removeFee(index)"
                    >
                        Remove
                    </button>

                </div>


                <div class="grid gap-4 sm:grid-cols-2">

                    {{-- LABEL --}}
                    <div>

                        <label
                            class="mb-2 block text-sm font-medium"
                        >
                            Fee Name *
                        </label>

                        <input
                            type="text"
                            :name="`fees[${index}][label]`"
                            x-model="fee.label"
                            class="admin-input"
                            placeholder="e.g. Service Fee"
                        >

                    </div>


                    {{-- TYPE --}}
                    <div>

                        <label
                            class="mb-2 block text-sm font-medium"
                        >
                            Fee Type *
                        </label>

                        <select
                            :name="`fees[${index}][type]`"
                            x-model="fee.type"
                            class="admin-input"
                        >

                            <option value="">
                                Select fee type
                            </option>

                            <option value="fixed">
                                Fixed Amount
                            </option>

                            <option value="range">
                                Amount Range
                            </option>

                            <option value="free">
                                Free
                            </option>

                            <option value="varies">
                                Varies
                            </option>

                        </select>

                    </div>


                    {{-- FIXED / RANGE MIN --}}
                    <div
                        x-show="
                            fee.type === 'fixed'
                            || fee.type === 'range'
                        "
                        x-cloak
                    >

                        <label
                            class="mb-2 block text-sm font-medium"
                        >
                            <span
                                x-text="
                                    fee.type === 'range'
                                        ? 'Minimum Amount'
                                        : 'Amount'
                                "
                            ></span>
                        </label>


                        <div class="relative">

                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500"
                            >
                                ₱
                            </span>

                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                :name="`fees[${index}][amount_min]`"
                                x-model="fee.amount_min"
                                class="admin-input pl-8"
                                placeholder="0.00"
                            >

                        </div>

                    </div>


                    {{-- RANGE MAX --}}
                    <div
                        x-show="fee.type === 'range'"
                        x-cloak
                    >

                        <label
                            class="mb-2 block text-sm font-medium"
                        >
                            Maximum Amount
                        </label>


                        <div class="relative">

                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500"
                            >
                                ₱
                            </span>

                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                :name="`fees[${index}][amount_max]`"
                                x-model="fee.amount_max"
                                class="admin-input pl-8"
                                placeholder="0.00"
                            >

                        </div>

                    </div>


                    {{-- NOTES --}}
                    <div class="sm:col-span-2">

                        <label
                            class="mb-2 block text-sm font-medium"
                        >
                            Notes
                        </label>

                        <textarea
                            :name="`fees[${index}][notes]`"
                            x-model="fee.notes"
                            rows="2"
                            class="admin-input"
                            placeholder="Optional conditions or details about this fee."
                        ></textarea>

                    </div>


                    {{-- OPTIONAL --}}
                    <div class="sm:col-span-2">

                        <label class="flex items-center gap-3">

                            <input
                                type="checkbox"
                                value="1"
                                :name="`fees[${index}][is_optional]`"
                                x-model="fee.is_optional"
                                class="rounded border-slate-300"
                            >

                            <span class="text-sm text-slate-700">
                                This fee is optional
                            </span>

                        </label>

                    </div>

                </div>

            </div>

        </template>


        <div
            x-show="fees.length === 0"
            class="rounded-xl border border-dashed border-slate-300 p-6 text-center"
        >

            <p class="text-sm text-slate-500">
                No fees added yet.
            </p>

            <button
                type="button"
                class="admin-secondary mt-3"
                @click="addFee()"
            >
                + Add First Fee
            </button>

        </div>

    </div>

</div>


<script>
    function governmentIdFeeEditor(initialFees) {
        const normalize = (fee = {}) => ({
            key:
                Date.now().toString()
                + Math.random().toString(36),

            label: fee.label ?? '',
            type: fee.type ?? 'fixed',

            amount_min:
                fee.amount_min ?? '',

            amount_max:
                fee.amount_max ?? '',

            is_optional:
                Boolean(fee.is_optional),

            notes:
                fee.notes ?? '',
        });

        return {
            fees: Array.isArray(initialFees)
                ? initialFees.map(normalize)
                : [],

            addFee() {
                this.fees.push(
                    normalize()
                );
            },

            removeFee(index) {
                this.fees.splice(index, 1);
            },
        };
    }
</script>