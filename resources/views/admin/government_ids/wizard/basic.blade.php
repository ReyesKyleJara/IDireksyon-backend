<section class="admin-panel space-y-6 bg-white p-6" aria-labelledby="wizard-basic-heading">
    <h3 id="wizard-basic-heading" class="text-lg font-semibold text-slate-900">General Information</h3>
    <div>
        <label for="name" class="mb-2 block text-sm font-medium">{{ $recordKind === 'document' ? 'Document' : 'ID' }} Name <span aria-hidden="true">*</span></label>
        <input id="name" name="name" value="{{ $value('name') }}" maxlength="255" required class="admin-input" placeholder="{{ $recordKind === 'document' ? 'e.g. PSA Birth Certificate' : 'e.g. Philippine Passport' }}" aria-describedby="name-help @error('name') name-error @enderror" @error('name') aria-invalid="true" @enderror>
        <p id="name-help" class="mt-1 text-xs text-slate-500">The only required basic field. Use the official name.</p>
        @error('name')<p id="name-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="level" class="mb-2 block text-sm font-medium">Level</label>
            <select id="level" name="level" class="admin-input">
                <option value="">Select level</option>
                @foreach(['Barangay', 'Municipal / LGU', 'National'] as $option)<option @selected($value('level') === $option)>{{ $option }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="category" class="mb-2 block text-sm font-medium">Category</label>
            <select id="category" name="category" class="admin-input">
                <option value="">Select category</option>
                @php
                    $categories = $recordKind === 'document'
                        ? ['Civil Registry', 'Clearance', 'Certification', 'Business / Permit', 'Tax / Property', 'Record / Report']
                        : ['Identity ID', 'Sector-Specific ID', 'Driving Credential', 'Professional Credential', 'Tax ID', 'Travel Document'];
                    $savedCategory = $value('category');
                    if ($savedCategory !== '' && !in_array($savedCategory, $categories, true)) $categories[] = $savedCategory;
                @endphp
                @foreach($categories as $option)<option @selected($savedCategory === $option)>{{ $option }}</option>@endforeach
            </select>
        </div>
    </div>
    @include('admin.government_ids.partials.agency-field', ['selectedAgencyId' => $governmentId?->agency_id])
    @if($recordKind === 'document')
        <div>
            <label for="issued_by" class="mb-2 block text-sm font-medium">Issuing office / department</label>
            <input id="issued_by" name="issued_by" maxlength="255" class="admin-input" value="{{ $value('issued_by') }}">
            @error('issued_by')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    @endif
    <div>
        <label for="description" class="mb-2 block text-sm font-medium">About this {{ $recordKind === 'document' ? 'Document' : 'ID' }}</label>
        <textarea id="description" name="description" rows="3" class="admin-input" placeholder="Briefly explain what this {{ $itemLabel }} is and what it is used for.">{{ $value('description') }}</textarea>
    </div>

    <div class="space-y-5 border-t border-slate-200 pt-5" aria-labelledby="wizard-validity-heading">
        <h3 id="wizard-validity-heading" class="text-lg font-semibold text-slate-900">Validity</h3>
        @include('admin.government_ids.partials.validity-field', [
            'selectedValidityType' => $governmentId?->validity_type,
            'selectedValidityValue' => $governmentId?->validity_value,
            'selectedValidityUnit' => $governmentId?->validity_unit,
        ])
    </div>

    <div class="space-y-5 border-t border-slate-200 pt-5" aria-labelledby="wizard-processing-heading">
        <h3 id="wizard-processing-heading" class="text-lg font-semibold text-slate-900">Processing Time</h3>
        @include('admin.government_ids.partials.processing-time-field', [
            'selectedProcessingType' => $governmentId?->processing_time_type,
            'selectedProcessingMin' => $governmentId?->processing_time_min,
            'selectedProcessingMax' => $governmentId?->processing_time_max,
            'selectedProcessingUnit' => $governmentId?->processing_time_unit,
        ])
    </div>

    <div class="border-t border-slate-200 pt-5" aria-label="Eligibility">
        @include('admin.government_ids.partials.eligibility-fields', [
            'eligibilityRecord' => $governmentId,
            'eligibilityHeadingClass' => 'text-lg font-semibold text-slate-900',
        ])
    </div>
</section>

<section class="admin-panel space-y-5 bg-white p-6" aria-labelledby="wizard-audience-heading">
    <div>
        <h3 id="wizard-audience-heading" class="text-lg font-semibold text-slate-900">Availability &amp; Audience</h3>
        <p class="mt-1 text-sm text-slate-500">Defines whether this item can currently be acquired and who it is primarily intended for.</p>
    </div>
    <div>
        <label for="application_availability" class="mb-2 block text-sm font-medium">Application Availability</label>
        <select id="application_availability" name="application_availability" class="admin-input" required aria-describedby="application-availability-help @error('application_availability') application-availability-error @enderror" @error('application_availability') aria-invalid="true" @enderror>
            @foreach(\App\Support\ApplicationAvailability::LABELS as $storedValue => $label)
                <option value="{{ $storedValue }}" @selected(old('application_availability', $governmentId?->application_availability ?? 'available') === $storedValue)>{{ $label }}</option>
            @endforeach
        </select>
        <p id="application-availability-help" class="mt-1 text-xs text-slate-500">Indicates whether new applications or requests for this ID/document are currently accepted.</p>
        @error('application_availability')<p id="application-availability-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="acquisition_type" class="mb-2 block text-sm font-medium">Acquisition Type</label>
        <select id="acquisition_type" name="acquisition_type" class="admin-input" required aria-describedby="acquisition-type-help @error('acquisition_type') acquisition-type-error @enderror" @error('acquisition_type') aria-invalid="true" @enderror>
            @foreach(\App\Support\AcquisitionType::LABELS as $storedValue => $label)
                <option value="{{ $storedValue }}" @selected(old('acquisition_type', $governmentId?->acquisition_type ?? 'general_public') === $storedValue)>{{ $label }}</option>
            @endforeach
        </select>
        <p id="acquisition-type-help" class="mt-1 text-xs text-slate-500">Indicates whether this ID/document is generally available or intended for a particular profession, employment context, sector, or special qualification.</p>
        @error('acquisition_type')<p id="acquisition-type-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="target_group" class="mb-2 block text-sm font-medium">Target Group / Qualification</label>
        <textarea id="target_group" name="target_group" rows="2" maxlength="1000" class="admin-input" placeholder="e.g. Seafarers, residents aged 60+, licensed professionals" aria-describedby="target-group-help @error('target_group') target-group-error @enderror" @error('target_group') aria-invalid="true" @enderror>{{ old('target_group', $governmentId?->target_group) }}</textarea>
        <p id="target-group-help" class="mt-1 text-xs text-slate-500">Optional. Briefly describe the specific group or qualification this ID/document is intended for.</p>
        @error('target_group')<p id="target-group-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</section>

<section class="admin-panel space-y-5 bg-white p-6" aria-labelledby="wizard-behavior-heading">
    <div>
        <h3 id="wizard-behavior-heading" class="text-lg font-semibold text-slate-900">IDireksyon Behavior</h3>
        <p class="mt-1 text-sm text-slate-500">Controls how IDireksyon presents and considers this item within its guidance and recommendation features.</p>
    </div>
    <div>
        <label for="guidance_mode" class="mb-2 block text-sm font-medium">Guidance Mode</label>
        <select id="guidance_mode" name="guidance_mode" class="admin-input" required aria-describedby="guidance-mode-help @error('guidance_mode') guidance-mode-error @enderror" @error('guidance_mode') aria-invalid="true" @enderror>
            @foreach(\App\Support\GuidanceMode::LABELS as $storedValue => $label)
                <option value="{{ $storedValue }}" @selected(old('guidance_mode', $governmentId?->guidance_mode ?? 'guided') === $storedValue)>{{ $label }}</option>
            @endforeach
        </select>
        <p id="guidance-mode-help" class="mt-1 text-xs text-slate-500">Indicates whether IDireksyon actively guides users through obtaining this item or keeps it mainly for reference/informational purposes.</p>
        @error('guidance_mode')<p id="guidance-mode-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <input type="hidden" name="include_in_smart_sequencing" value="0">
        <label for="include_in_smart_sequencing" class="flex items-center gap-2 text-sm font-medium">
            <input id="include_in_smart_sequencing" type="checkbox" name="include_in_smart_sequencing" value="1" @checked(old('include_in_smart_sequencing', $governmentId?->include_in_smart_sequencing ?? true)) aria-describedby="smart-sequencing-inclusion-help @error('include_in_smart_sequencing') smart-sequencing-inclusion-error @enderror" @error('include_in_smart_sequencing') aria-invalid="true" @enderror>
            Include in Smart Sequencing
        </label>
        <p id="smart-sequencing-inclusion-help" class="mt-1 text-xs text-slate-500">Allows this ID/document to be considered as an acquisition target in Smart Sequencing and Roadmap recommendations.</p>
        @error('include_in_smart_sequencing')<p id="smart-sequencing-inclusion-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</section>
