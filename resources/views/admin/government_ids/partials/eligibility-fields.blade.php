@php
    $record = $eligibilityRecord ?? null;
    $legacyEligibility = $record
        && $record->eligibility_age_type === null
        && $record->eligibility_min_age === null
        && $record->eligibility_max_age === null
        && $record->eligibility_citizenship === null
        && $record->eligibility_residency === null
        && $record->eligibility_residency_custom === null
        && $record->eligibility_other_conditions === null
        && filled($record->eligibility);
    $otherConditions = $legacyEligibility ? $record->eligibility : $record?->eligibility_other_conditions;
    // Invalid array input should redisplay as blank rather than break the form.
    $eligibilityValue = function ($field, $fallback = '') {
        $value = old($field, $fallback);
        return is_scalar($value) ? (string) $value : '';
    };
@endphp

<fieldset
    class="space-y-5"
    x-data="{
        ageType: @js($eligibilityValue('eligibility_age_type', $record?->eligibility_age_type)),
        residency: @js($eligibilityValue('eligibility_residency', $record?->eligibility_residency))
    }"
>
    <legend class="text-base font-semibold text-slate-900">Eligibility</legend>
    <p class="text-sm text-slate-500">
        Select the researched conditions. Leave a selection blank if it has not been researched yet.
    </p>

    @if($legacyEligibility)
        <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            Existing eligibility text is preserved in Other Eligibility Conditions below.
            Review it, select the matching options, and remove any repeated wording from the notes.
        </p>
    @endif

    <div>
        <label for="eligibility_age_type" class="mb-2 block text-sm font-medium">Age Requirement</label>
        <select id="eligibility_age_type" name="eligibility_age_type" x-model="ageType" class="admin-input">
            <option value="">Select requirement</option>
            <option value="none">No age requirement</option>
            <option value="minimum">Minimum age only</option>
            <option value="range">Age range</option>
        </select>
        @error('eligibility_age_type')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2" x-show="ageType === 'minimum' || ageType === 'range'" x-cloak>
        <div>
            <label for="eligibility_min_age" class="mb-2 block text-sm font-medium">Minimum Age (years)</label>
            <input id="eligibility_min_age" name="eligibility_min_age" type="number" min="0" max="150" step="1"
                value="{{ $eligibilityValue('eligibility_min_age', $record?->eligibility_min_age) }}"
                :disabled="ageType !== 'minimum' && ageType !== 'range'"
                :required="ageType === 'minimum' || ageType === 'range'" class="admin-input">
            @error('eligibility_min_age')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div x-show="ageType === 'range'" x-cloak>
            <label for="eligibility_max_age" class="mb-2 block text-sm font-medium">Maximum Age (years)</label>
            <input id="eligibility_max_age" name="eligibility_max_age" type="number" min="0" max="150" step="1"
                value="{{ $eligibilityValue('eligibility_max_age', $record?->eligibility_max_age) }}"
                :disabled="ageType !== 'range'" :required="ageType === 'range'" class="admin-input">
            <p class="mt-2 text-xs text-slate-500">Both minimum and maximum ages are included.</p>
            @error('eligibility_max_age')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="eligibility_citizenship" class="mb-2 block text-sm font-medium">Citizenship Requirement</label>
        <select id="eligibility_citizenship" name="eligibility_citizenship" class="admin-input">
            @foreach(['' => 'Select requirement', 'none' => 'No specific citizenship requirement', 'filipino' => 'Filipino citizen required'] as $value => $label)
                <option value="{{ $value }}" @selected($eligibilityValue('eligibility_citizenship', $record?->eligibility_citizenship) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('eligibility_citizenship')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="eligibility_residency" class="mb-2 block text-sm font-medium">Residency Requirement</label>
        <select id="eligibility_residency" name="eligibility_residency" x-model="residency" class="admin-input">
            <option value="">Select requirement</option>
            <option value="none">No specific residency requirement</option>
            <option value="philippines">Philippine resident required</option>
            <option value="santa_maria">Santa Maria, Bulacan resident required</option>
            <option value="custom">Other / Custom</option>
        </select>
        @error('eligibility_residency')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div x-show="residency === 'custom'" x-cloak>
        <label for="eligibility_residency_custom" class="mb-2 block text-sm font-medium">Custom Residency Requirement</label>
        <input id="eligibility_residency_custom" name="eligibility_residency_custom" type="text" maxlength="255"
            value="{{ $eligibilityValue('eligibility_residency_custom', $record?->eligibility_residency_custom) }}"
            :disabled="residency !== 'custom'" :required="residency === 'custom'"
            class="admin-input" placeholder="Describe the required residency">
        @error('eligibility_residency_custom')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="eligibility_other_conditions" class="mb-2 block text-sm font-medium">Other Eligibility Conditions</label>
        <textarea id="eligibility_other_conditions" name="eligibility_other_conditions" rows="4" maxlength="10000"
            class="admin-input" placeholder="Conditions or exceptions not covered by the selections above">{{ $eligibilityValue('eligibility_other_conditions', $otherConditions) }}</textarea>
        @error('eligibility_other_conditions')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</fieldset>
