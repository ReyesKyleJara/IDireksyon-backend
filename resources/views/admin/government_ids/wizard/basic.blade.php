<section class="admin-panel space-y-6 bg-white p-6" aria-labelledby="wizard-basic-heading">
    <h3 id="wizard-basic-heading" class="text-lg font-semibold text-slate-900">Basic Information</h3>
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
</section>

<section class="admin-panel space-y-5 bg-white p-6" aria-labelledby="wizard-validity-heading">
    <h3 id="wizard-validity-heading" class="text-lg font-semibold text-slate-900">Validity</h3>
    @include('admin.government_ids.partials.validity-field', [
        'selectedValidityType' => $governmentId?->validity_type,
        'selectedValidityValue' => $governmentId?->validity_value,
        'selectedValidityUnit' => $governmentId?->validity_unit,
    ])
</section>

<section class="admin-panel space-y-5 bg-white p-6" aria-labelledby="wizard-processing-heading">
    <h3 id="wizard-processing-heading" class="text-lg font-semibold text-slate-900">Processing Time</h3>
    @include('admin.government_ids.partials.processing-time-field', [
        'selectedProcessingType' => $governmentId?->processing_time_type,
        'selectedProcessingMin' => $governmentId?->processing_time_min,
        'selectedProcessingMax' => $governmentId?->processing_time_max,
        'selectedProcessingUnit' => $governmentId?->processing_time_unit,
    ])
</section>

<section class="admin-panel bg-white p-6" aria-label="Eligibility">
    @include('admin.government_ids.partials.eligibility-fields', [
        'eligibilityRecord' => $governmentId,
        'eligibilityHeadingClass' => 'text-lg font-semibold text-slate-900',
    ])
</section>
