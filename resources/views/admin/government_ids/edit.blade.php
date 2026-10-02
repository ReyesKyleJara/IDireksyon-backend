@extends('admin.layouts.app')

@section('title', 'Edit ID or Credential | IDireksyon')
@section('page_title', 'Edit ID or Credential')

@section('content')

<div class="mx-auto max-w-4xl">

    <div class="mb-7">

        <p class="admin-eyebrow mb-2">
            IDs & Credentials
        </p>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            Edit ID or Credential
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            Update the researched information for {{ $governmentId->name }}.
        </p>

    </div>


    <form
        method="POST"
        action="{{ route('admin.government-ids.update', $governmentId) }}"
        class="space-y-6"
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
                <div class="sm:col-span-2">

                    <label
                        for="purpose"
                        class="mb-2 block text-sm font-medium"
                    >
                        Purpose / Use
                    </label>

                    <textarea
                        id="purpose"
                        name="purpose"
                        rows="3"
                        class="admin-input"
                    >{{ old('purpose', $governmentId->purpose) }}</textarea>

                </div>


                {{-- DESCRIPTION --}}
                <div class="sm:col-span-2">

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


                {{-- STRUCTURED VALIDITY --}}
                @include(
                    'admin.government_ids.partials.validity-field',
                    [
                        'selectedValidityType' => $governmentId->validity_type,
                        'selectedValidityValue' => $governmentId->validity_value,
                        'selectedValidityUnit' => $governmentId->validity_unit,
                    ]
                )

            </div>

        </section>


        {{-- ELIGIBILITY --}}
        <section class="admin-panel p-6" aria-label="Eligibility">
            @include('admin.government_ids.partials.eligibility-fields', [
                'eligibilityRecord' => $governmentId,
            ])
        </section>


        {{-- REQUIREMENTS & PREREQUISITES --}}
        <section class="admin-panel p-6">

            <div class="mb-6">

                <h2 class="text-lg font-semibold text-slate-900">
                    Requirements & Prerequisites
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Human-readable application requirements for residents.
                </p>

            </div>


            <div class="grid gap-6">

                {{-- REQUIREMENTS --}}
                <div>

                    <label
                        for="requirements"
                        class="mb-2 block text-sm font-medium"
                    >
                        Requirements
                    </label>

                    <textarea
                        id="requirements"
                        name="requirements"
                        rows="6"
                        class="admin-input"
                    >{{ old('requirements', $governmentId->requirements) }}</textarea>

                </div>


                {{-- PREREQUISITES --}}
                <div>

                    <label
                        for="prerequisite_notes"
                        class="mb-2 block text-sm font-medium"
                    >
                        Prerequisites / Dependencies
                    </label>

                    <textarea
                        id="prerequisite_notes"
                        name="prerequisite_notes"
                        rows="4"
                        class="admin-input"
                    >{{ old('prerequisite_notes', $governmentId->prerequisite_notes) }}</textarea>

                    <p class="mt-2 text-xs text-slate-500">
                        This is for readable guidance. Smart sequencing rules will be stored separately.
                    </p>

                </div>

            </div>

        </section>


        {{-- APPLICATION GUIDE --}}
        <section class="admin-panel p-6">

            <div class="mb-6">

                <h2 class="text-lg font-semibold text-slate-900">
                    Application Guide
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Costs, processing information, and application procedures.
                </p>

            </div>


            <div class="grid gap-6 sm:grid-cols-2">

                {{-- FEES --}}
                @include(
                    'admin.government_ids.partials.fee-items-field',
                    [
                        'selectedFees' => $governmentId->fees,
                    ]
                )


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


                {{-- APPLICATION PROCESS --}}
                <div class="sm:col-span-2">

                    <label
                        for="application_process"
                        class="mb-2 block text-sm font-medium"
                    >
                        Application Process / Steps
                    </label>

                    <textarea
                        id="application_process"
                        name="application_process"
                        rows="7"
                        class="admin-input"
                    >{{ old('application_process', $governmentId->application_process) }}</textarea>

                </div>


                {{-- RENEWAL PROCESS --}}
                <div class="sm:col-span-2">

                    <label
                        for="renewal_process"
                        class="mb-2 block text-sm font-medium"
                    >
                        Renewal Process
                    </label>

                    <textarea
                        id="renewal_process"
                        name="renewal_process"
                        rows="4"
                        class="admin-input"
                    >{{ old('renewal_process', $governmentId->renewal_process) }}</textarea>

                </div>


                {{-- REPLACEMENT PROCESS --}}
                <div class="sm:col-span-2">

                    <label
                        for="replacement_process"
                        class="mb-2 block text-sm font-medium"
                    >
                        Replacement Process
                    </label>

                    <textarea
                        id="replacement_process"
                        name="replacement_process"
                        rows="4"
                        class="admin-input"
                    >{{ old('replacement_process', $governmentId->replacement_process) }}</textarea>

                </div>

            </div>

        </section>


        @include('admin.government_ids.partials.office-links-field', [
            'selectedOffices' => $governmentId->offices,
        ])

        {{-- SOURCES & VERIFICATION --}}
        <section class="admin-panel p-6">

            <div class="mb-6">

                <h2 class="text-lg font-semibold text-slate-900">
                    Sources
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Record useful official websites and reference material.
                </p>

            </div>


            <div class="grid gap-6">

                {{-- OFFICIAL LINK --}}
                <div>

                    <label
                        for="official_link"
                        class="mb-2 block text-sm font-medium"
                    >
                        Official Website / Application Link
                    </label>

                    <input
                        id="official_link"
                        name="official_link"
                        type="url"
                        value="{{ old(
                            'official_link',
                            $governmentId->official_link
                        ) }}"
                        class="admin-input"
                        placeholder="https://..."
                    >

                    @error('official_link')
                        <p class="mt-2 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                </div>


                {{-- OFFICIAL SOURCES --}}
                <div>

                    <label
                        for="official_sources"
                        class="mb-2 block text-sm font-medium"
                    >
                        Official Sources
                    </label>

                    <textarea
                        id="official_sources"
                        name="official_sources"
                        rows="5"
                        class="admin-input"
                    >{{ old('official_sources', $governmentId->official_sources) }}</textarea>

                </div>


            </div>

        </section>


        {{-- ACTIONS --}}
        <div class="flex justify-end gap-3">

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

    </form>

</div>

@endsection