@extends('admin.layouts.app')

@section('title', 'Edit ID or Credential | IDireksyon')
@section('page_title', 'Edit ID or Credential')

@section('content')

@include('admin.government_ids.partials.editor-styles')

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