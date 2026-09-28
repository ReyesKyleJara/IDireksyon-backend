<div
    x-data="{
        feeType: @js(
            old(
                'fee_type',
                $selectedFeeType ?? ''
            )
        )
    }"
>

    <label
        for="fee_type"
        class="mb-2 block text-sm font-medium"
    >
        Application Fee / Costs
    </label>


    <select
        id="fee_type"
        name="fee_type"
        x-model="feeType"
        class="admin-input"
    >

        <option value="">
            Select fee type
        </option>

        <option value="fixed">
            Fixed Amount
        </option>

        <option value="range">
            Price Range
        </option>

        <option value="free">
            Free
        </option>

        <option value="varies">
            Varies
        </option>

        <option value="not_applicable">
            Not Applicable
        </option>

    </select>


    {{-- FIXED / RANGE AMOUNTS --}}
    <div
        x-show="
            feeType === 'fixed'
            || feeType === 'range'
        "
        x-cloak
        class="mt-4 grid gap-4 sm:grid-cols-2"
    >

        <div>

            <label
                for="fee_min"
                class="mb-2 block text-sm font-medium"
            >
                <span
                    x-text="
                        feeType === 'range'
                            ? 'Minimum Amount'
                            : 'Amount'
                    "
                ></span>
            </label>


            <div class="relative">

                <span
                    class="pointer-events-none absolute
                           inset-y-0 left-0 flex
                           items-center pl-3
                           text-sm text-slate-500"
                >
                    ₱
                </span>

                <input
                    id="fee_min"
                    name="fee_min"
                    type="number"
                    min="0"
                    step="0.01"
                    value="{{ old(
                        'fee_min',
                        $selectedFeeMin ?? ''
                    ) }}"
                    class="admin-input pl-8"
                    placeholder="950.00"
                >

            </div>

        </div>


        <div
            x-show="feeType === 'range'"
            x-cloak
        >

            <label
                for="fee_max"
                class="mb-2 block text-sm font-medium"
            >
                Maximum Amount
            </label>


            <div class="relative">

                <span
                    class="pointer-events-none absolute
                           inset-y-0 left-0 flex
                           items-center pl-3
                           text-sm text-slate-500"
                >
                    ₱
                </span>

                <input
                    id="fee_max"
                    name="fee_max"
                    type="number"
                    min="0"
                    step="0.01"
                    value="{{ old(
                        'fee_max',
                        $selectedFeeMax ?? ''
                    ) }}"
                    class="admin-input pl-8"
                    placeholder="1200.00"
                >

            </div>

        </div>

    </div>


    {{-- CURRENCY --}}
    <input
        type="hidden"
        name="fee_currency"
        value="PHP"
    >


    {{-- NOTES --}}
    <div class="mt-4">

        <label
            for="fee_notes"
            class="mb-2 block text-sm font-medium"
        >
            Fee Notes
        </label>

        <textarea
            id="fee_notes"
            name="fee_notes"
            rows="3"
            class="admin-input"
            placeholder="Optional notes about additional fees, processing types, or other costs."
        >{{ old(
            'fee_notes',
            $selectedFeeNotes ?? ''
        ) }}</textarea>

        <p class="mt-2 text-xs text-slate-500">
            Use this for additional charges or special fee conditions.
        </p>

    </div>


    @error('fee_type')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror


    @error('fee_min')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror


    @error('fee_max')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror


    @error('fee_notes')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror

</div>