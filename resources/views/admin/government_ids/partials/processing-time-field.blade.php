<div
    x-data="{
        processingType: @js(
            old(
                'processing_time_type',
                $selectedProcessingType ?? ''
            )
        )
    }"
>

    <label
        for="processing_time_type"
        class="mb-2 block text-sm font-medium"
    >
        Processing Time
    </label>

    <select
        id="processing_time_type"
        name="processing_time_type"
        x-model="processingType"
        class="admin-input"
    >
        <option value="">
            Select processing type
        </option>

        <option value="fixed">
            Fixed Duration
        </option>

        <option value="range">
            Range
        </option>

        <option value="same_day">
            Same Day
        </option>

        <option value="varies">
            Varies
        </option>

        <option value="not_applicable">
            Not Applicable
        </option>
    </select>


    <div
        x-show="
            processingType === 'fixed'
            || processingType === 'range'
        "
        x-cloak
        class="mt-4 grid gap-4 sm:grid-cols-2"
    >

        <div>

            <label
                for="processing_time_min"
                class="mb-2 block text-sm font-medium"
            >
                <span
                    x-text="
                        processingType === 'range'
                            ? 'Minimum'
                            : 'Duration'
                    "
                ></span>
            </label>

            <input
                id="processing_time_min"
                name="processing_time_min"
                type="number"
                min="1"
                max="999"
                value="{{ old(
                    'processing_time_min',
                    $selectedProcessingMin ?? ''
                ) }}"
                class="admin-input"
                placeholder="e.g. 6"
            >

        </div>


        <div
            x-show="processingType === 'range'"
            x-cloak
        >

            <label
                for="processing_time_max"
                class="mb-2 block text-sm font-medium"
            >
                Maximum
            </label>

            <input
                id="processing_time_max"
                name="processing_time_max"
                type="number"
                min="1"
                max="999"
                value="{{ old(
                    'processing_time_max',
                    $selectedProcessingMax ?? ''
                ) }}"
                class="admin-input"
                placeholder="e.g. 12"
            >

        </div>

    </div>


    <div
        x-show="
            processingType === 'fixed'
            || processingType === 'range'
        "
        x-cloak
        class="mt-4"
    >

        <label
            for="processing_time_unit"
            class="mb-2 block text-sm font-medium"
        >
            Unit
        </label>

        <select
            id="processing_time_unit"
            name="processing_time_unit"
            class="admin-input"
        >

            <option value="">
                Select unit
            </option>

            <option
                value="calendar_day"
                @selected(
                    old(
                        'processing_time_unit',
                        $selectedProcessingUnit ?? ''
                    ) === 'calendar_day'
                )
            >
                Calendar Days
            </option>

            <option
                value="working_day"
                @selected(
                    old(
                        'processing_time_unit',
                        $selectedProcessingUnit ?? ''
                    ) === 'working_day'
                )
            >
                Working Days
            </option>

            <option
                value="week"
                @selected(
                    old(
                        'processing_time_unit',
                        $selectedProcessingUnit ?? ''
                    ) === 'week'
                )
            >
                Weeks
            </option>

            <option
                value="month"
                @selected(
                    old(
                        'processing_time_unit',
                        $selectedProcessingUnit ?? ''
                    ) === 'month'
                )
            >
                Months
            </option>

        </select>

    </div>


    @error('processing_time_type')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror

    @error('processing_time_min')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror

    @error('processing_time_max')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror

    @error('processing_time_unit')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror

</div>