@extends('admin.layouts.app')

@section('title', 'Edit ID or Credential | IDireksyon')
@section('page_title', 'Edit ID or Credential')

@section('content')

<style>
    .id-editor { width: 100%; min-width: 0; }
    .id-editor .editor-heading { margin-bottom: 24px; }
    .id-editor .editor-heading h1 { margin-top: 16px; font-size: 26px; font-weight: 650; line-height: 1.3; letter-spacing: -.025em; overflow-wrap: anywhere; }
    .id-editor .editor-form { padding: 32px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
    .id-editor .editor-form > .admin-panel { margin: 0; padding: 28px 0; border: 0; border-bottom: 1px solid #e2e8f0; border-radius: 0; }
    .id-editor .editor-form > .admin-panel:first-of-type { padding-top: 0; }
    .id-editor .editor-form > .admin-panel > div:first-child { margin-bottom: 20px; }
    .id-editor .editor-form > section > div > h2,
    .id-editor .editor-form > section > div > div > h2,
    .id-editor .editor-form > section > fieldset > legend,
    .id-editor .editor-timing h2 { font-size: 16px; font-weight: 600; line-height: 1.5; }
    .id-editor .editor-timing { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 32px; padding: 28px 0; border-bottom: 1px solid #e2e8f0; }
    .id-editor .editor-timing > section { min-width: 0; padding: 0; border: 0; border-radius: 0; }
    .id-editor .editor-timing > section + section { padding-left: 32px; border-left: 1px solid #e2e8f0; }
    .id-editor .editor-actions { position: sticky; bottom: 0; z-index: 20; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-top: 20px; padding: 16px 0; background: #fff; border-top: 1px solid #e2e8f0; }
    .id-editor .editor-actions p { margin: 0; max-width: 440px; font-size: 12px; line-height: 1.5; color: #64748b; }
    .id-editor .editor-buttons { display: flex; gap: 12px; margin-left: auto; }
    @media (min-width: 900px) {
        .id-editor .editor-description, .id-editor .editor-purpose { grid-column: span 1 / span 1; }
    }
    @media (max-width: 1023px) {
        .id-editor .editor-timing { grid-template-columns: minmax(0, 1fr); gap: 24px; }
        .id-editor .editor-timing > section + section { padding-left: 0; border-left: 0; padding-top: 24px; border-top: 1px solid #e2e8f0; }
    }
    @media (max-width: 640px) {
        .id-editor .editor-form { padding: 20px; }
        .id-editor .editor-heading h1 { font-size: 23px; }
    }
</style>

<div class="id-editor">
    <header class="editor-heading">
        <a href="{{ route('admin.government-ids.show', $governmentId) }}" class="text-sm text-slate-600 hover:underline">← Back to ID details</a>
        <h1>{{ $governmentId->name }}</h1>
        <p class="mt-2 text-sm text-slate-500">Edit ID details</p>
    </header>

    <form
        method="POST"
        action="{{ route('admin.government-ids.update', $governmentId) }}"
        class="editor-form"
    >

        @csrf
        @method('PUT')


        @if ($errors->any())

            <div class="rounded-xl border border-red-200 bg-red-50 p-4">

                <p class="text-sm font-semibold text-red-700">
                    Please check the highlighted fields.
                </p>

            </div>

        @endif


        {{-- BASIC INFORMATION --}}
        <section class="admin-panel p-6">

            <div class="mb-6">

                <h2 class="text-lg font-semibold text-slate-900">
                    Basic Information
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    General identification and classification details.
                </p>

            </div>


            <div class="grid gap-6 sm:grid-cols-2">

                {{-- ID NAME --}}
                <div class="sm:col-span-2">

                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium"
                    >
                        ID Name *
                    </label>

                    <input
                        id="name"
                        name="name"
                        value="{{ old('name', $governmentId->name) }}"
                        class="admin-input"
                        required
                    >

                    @error('name')
                        <p class="mt-2 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                </div>


                {{-- LEVEL --}}
                <div>

                    <label
                        for="level"
                        class="mb-2 block text-sm font-medium"
                    >
                        Level
                    </label>

                    <select
                        id="level"
                        name="level"
                        class="admin-input"
                    >

                        <option value="">
                            Select level
                        </option>

                        <option
                            value="Barangay"
                            @selected(
                                old(
                                    'level',
                                    $governmentId->level
                                ) === 'Barangay'
                            )
                        >
                            Barangay
                        </option>

                        <option
                            value="Municipal / LGU"
                            @selected(
                                old(
                                    'level',
                                    $governmentId->level
                                ) === 'Municipal / LGU'
                            )
                        >
                            Municipal / LGU
                        </option>

                        <option
                            value="National"
                            @selected(
                                old(
                                    'level',
                                    $governmentId->level
                                ) === 'National'
                            )
                        >
                            National
                        </option>

                    </select>

                </div>


                {{-- CATEGORY --}}
                <div>

                    <label
                        for="category"
                        class="mb-2 block text-sm font-medium"
                    >
                        Category
                    </label>

                    <select
                        id="category"
                        name="category"
                        class="admin-input"
                    >

                        <option value="">
                            Select category
                        </option>

                        @foreach([
                            'Identity ID',
                            'Sector-Specific ID',
                            'Driving Credential',
                            'Professional Credential',
                            'Tax ID',
                            'Travel Document'
                        ] as $category)

                            <option
                                value="{{ $category }}"
                                @selected(
                                    old(
                                        'category',
                                        $governmentId->category
                                    ) === $category
                                )
                            >
                                {{ $category }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- ISSUING AGENCY --}}
                <div class="sm:col-span-2">

                    @include(
                        'admin.government_ids.partials.agency-field',
                        [
                            'selectedAgencyId' => $governmentId->agency_id,
                        ]
                    )

                </div>


                {{-- PURPOSE --}}
                <div class="sm:col-span-2 editor-purpose">

                    <label
                        for="purpose"
                        class="mb-2 block text-sm font-medium"
                    >
                        Purpose / Use
                    </label>

                    <textarea
                        id="purpose"
                        name="purpose"
                        rows="4"
                        class="admin-input"
                    >{{ old('purpose', $governmentId->purpose) }}</textarea>

                </div>


                {{-- DESCRIPTION --}}
                <div class="sm:col-span-2 editor-description">

                    <label
                        for="description"
                        class="mb-2 block text-sm font-medium"
                    >
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="admin-input"
                    >{{ old('description', $governmentId->description) }}</textarea>

                </div>



            </div>

        </section>


        {{-- ELIGIBILITY --}}
        <section class="admin-panel p-6" aria-label="Eligibility">
            @include('admin.government_ids.partials.eligibility-fields', [
                'eligibilityRecord' => $governmentId,
                'eligibilityHeadingClass' => 'text-lg font-semibold text-slate-900',
            ])
        </section>


        {{-- REQUIREMENTS --}}
        @include('admin.government_ids.partials.checklists', ['editable' => true])

        {{-- Preserve existing resident API text while editing structured checklists. --}}
        <div hidden>
            <textarea name="requirements">{{ old('requirements', $governmentId->requirements) }}</textarea>
            <textarea name="prerequisite_notes">{{ old('prerequisite_notes', $governmentId->prerequisite_notes) }}</textarea>
        </div>

        @include('admin.government_ids.partials.fees-modal', ['selectedFees' => $governmentId->fees])

        <div class="editor-timing">
        {{-- VALIDITY --}}
        <section class="admin-panel p-6" aria-label="Validity">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-slate-900">Validity</h2>
                <p class="mt-1 text-sm text-slate-500">How long the ID remains valid after it is issued.</p>
            </div>
                {{-- STRUCTURED VALIDITY --}}
                @include(
                    'admin.government_ids.partials.validity-field',
                    [
                        'selectedValidityType' => $governmentId->validity_type,
                        'selectedValidityValue' => $governmentId->validity_value,
                        'selectedValidityUnit' => $governmentId->validity_unit,
                    ]
                )

        </section>

        {{-- PROCESSING TIME --}}
        <section class="admin-panel p-6">

            <div class="mb-6">

                <h2 class="text-lg font-semibold text-slate-900">
                    Processing Time
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Record the estimated processing time.
                </p>

            </div>


            <div>

                {{-- PROCESSING TIME --}}
                @include(
                    'admin.government_ids.partials.processing-time-field',
                    [
                        'selectedProcessingType' => $governmentId->processing_time_type,
                        'selectedProcessingMin' => $governmentId->processing_time_min,
                        'selectedProcessingMax' => $governmentId->processing_time_max,
                        'selectedProcessingUnit' => $governmentId->processing_time_unit,
                    ]
                )


            </div>

        </section>


        </div>

        @include('admin.government_ids.partials.application-guide-editor')



        @include('admin.government_ids.partials.office-links-field', [
            'selectedOffices' => $governmentId->offices,
            'officeModal' => true,
        ])

        {{-- ACTIONS --}}
        <div class="editor-actions">
            <p>Checklists save separately. Use Save Changes for the other sections.</p>
            <div class="editor-buttons">

            <a
                href="{{ route('admin.government-ids.index') }}"
                class="admin-secondary"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="admin-primary"
            >
                Save Changes
            </button>

            </div>
        </div>

    </form>

</div>

@endsection