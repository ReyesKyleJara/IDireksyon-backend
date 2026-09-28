<div
    class="sm:col-span-2"
    x-data="{
        validityType: @js(
            old(
                'validity_type',
                $selectedValidityType ?? ''
            )
        )
    }"
>

    <label
        for="validity_type"
        class="mb-2 block text-sm font-medium"
    >
        Validity Period
    </label>


    <select
        id="validity_type"
        name="validity_type"
        x-model="validityType"
        class="admin-input"
    >

        <option value="">
            Select validity type
        </option>

        <option value="fixed">
            Fixed Period
        </option>

        <option value="lifetime">
            Lifetime
        </option>

        <option value="no_expiration">
            No Expiration
        </option>

        <option value="until_age">
            Until Specific Age
        </option>

        <option value="varies">
            Varies
        </option>

        <option value="not_applicable">
            Not Applicable
        </option>

    </select>


    {{-- VALUE --}}
    <div
        x-show="
            validityType === 'fixed'
            || validityType === 'until_age'
        "
        x-cloak
        class="mt-4"
    >

        <label
            for="validity_value"
            class="mb-2 block text-sm font-medium"
        >

            <span
                x-text="
                    validityType === 'until_age'
                        ? 'Valid Until Age'
                        : 'Duration'
                "
            ></span>

        </label>


        <input
            id="validity_value"
            name="validity_value"
            type="number"
            min="1"
            max="999"
            value="{{ old(
                'validity_value',
                $selectedValidityValue ?? ''
            ) }}"
            class="admin-input"
            :placeholder="
                validityType === 'until_age'
                    ? 'e.g. 60'
                    : 'e.g. 10'
            "
        >

    </div>


    {{-- UNIT --}}
    <div
        x-show="validityType === 'fixed'"
        x-cloak
        class="mt-4"
    >

        <label
            for="validity_unit"
            class="mb-2 block text-sm font-medium"
        >
            Unit
        </label>


        <select
            id="validity_unit"
            name="validity_unit"
            class="admin-input"
        >

            <option value="">
                Select unit
            </option>


            <option
                value="day"
                @selected(
                    old(
                        'validity_unit',
                        $selectedValidityUnit ?? ''
                    ) === 'day'
                )
            >
                Days
            </option>


            <option
                value="week"
                @selected(
                    old(
                        'validity_unit',
                        $selectedValidityUnit ?? ''
                    ) === 'week'
                )
            >
                Weeks
            </option>


            <option
                value="month"
                @selected(
                    old(
                        'validity_unit',
                        $selectedValidityUnit ?? ''
                    ) === 'month'
                )
            >
                Months
            </option>


            <option
                value="year"
                @selected(
                    old(
                        'validity_unit',
                        $selectedValidityUnit ?? ''
                    ) === 'year'
                )
            >
                Years
            </option>

        </select>

    </div>


    @error('validity_type')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror


    @error('validity_value')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror


    @error('validity_unit')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror

</div>