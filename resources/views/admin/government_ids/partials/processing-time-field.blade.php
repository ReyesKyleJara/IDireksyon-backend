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
        <option value="" @selected(old('processing_time_type', $selectedProcessingType ?? '') === '')>
            Select processing type
        </option>

        <option value="fixed" @selected(old('processing_time_type', $selectedProcessingType ?? '') === 'fixed')>
            Fixed Duration
        </option>

        <option value="range" @selected(old('processing_time_type', $selectedProcessingType ?? '') === 'range')>
            Range
        </option>

        <option value="same_day" @selected(old('processing_time_type', $selectedProcessingType ?? '') === 'same_day')>
            Same Day
        </option>

        <option value="varies" @selected(old('processing_time_type', $selectedProcessingType ?? '') === 'varies')>
            Varies
        </option>

        <option value="not_applicable" @selected(old('processing_time_type', $selectedProcessingType ?? '') === 'not_applicable')>
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

            <option value="">Select unit</option>
            @foreach(['minute' => 'Minutes', 'hour' => 'Hours'] as $code => $label)<option value="{{ $code }}" @selected(old('processing_time_unit', $selectedProcessingUnit ?? '') === $code)>{{ $label }}</option>@endforeach

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


    <div class="mt-4">
        <label for="processing_time_note" class="mb-2 block text-sm font-medium">Processing Time Note</label>
        <textarea id="processing_time_note" name="processing_time_note" rows="3" class="admin-input"
            placeholder="Add additional information about processing times, delays, or special conditions."
            aria-describedby="processing-time-note-help">{{ old('processing_time_note', $selectedProcessingNote ?? '') }}</textarea>
        <p id="processing-time-note-help" class="mt-1 text-sm text-slate-500">Optional. Explain conditions that may affect the estimated processing time.</p>
        @error('processing_time_note')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
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