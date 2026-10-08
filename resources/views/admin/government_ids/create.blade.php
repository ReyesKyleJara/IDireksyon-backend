@extends('admin.layouts.app')

@section('title', 'Add ID or Credential | IDireksyon')
@section('page_title', 'Add ID or Credential')

@section('content')

<div class="mx-auto max-w-4xl">

    <div class="mb-7">

        <p class="admin-eyebrow mb-2">
            IDs & Credentials
        </p>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            Add ID or Credential
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            Add and maintain researched information for a government ID or credential.
        </p>

    </div>


    <form
        method="POST"
        action="{{ route('admin.government-ids.store') }}"
        class="space-y-6"
    >

        @csrf


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
                        value="{{ old('name') }}"
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
                            @selected(old('level') === 'Barangay')
                        >
                            Barangay
                        </option>

                        <option
                            value="Municipal / LGU"
                            @selected(old('level') === 'Municipal / LGU')
                        >
                            Municipal / LGU
                        </option>

                        <option
                            value="National"
                            @selected(old('level') === 'National')
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
                                @selected(old('category') === $category)
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
                            'selectedAgencyId' => null,
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
                        placeholder="What is this ID primarily used for?"
                    >{{ old('purpose') }}</textarea>

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
                        placeholder="Brief description of the ID or credential."
                    >{{ old('description') }}</textarea>

                </div>


                {{-- STRUCTURED VALIDITY --}}
                @include(
                    'admin.government_ids.partials.validity-field',
                    [
                        'selectedValidityType' => null,
                        'selectedValidityValue' => null,
                        'selectedValidityUnit' => null,
                    ]
                )

            </div>

        </section>


        {{-- ELIGIBILITY --}}
        <section class="admin-panel p-6" aria-label="Eligibility">
            @include('admin.government_ids.partials.eligibility-fields', [
                'eligibilityRecord' => null,
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
                        placeholder="List the documentary and application requirements."
                    >{{ old('requirements') }}</textarea>

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
                        placeholder="Describe any IDs or documents that should be obtained first."
                    >{{ old('prerequisite_notes') }}</textarea>

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
                    Fees & Processing Time
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Manage the costs and estimated processing time.
                </p>

            </div>


            <div class="grid gap-6 sm:grid-cols-2">

                {{-- FEES --}}
                @include(
                    'admin.government_ids.partials.fee-items-field',
                    [
                        'selectedFees' => [],
                    ]
                )


                {{-- PROCESSING TIME --}}
                @include(
                    'admin.government_ids.partials.processing-time-field',
                    [
                        'selectedProcessingType' => null,
                        'selectedProcessingMin' => null,
                        'selectedProcessingMax' => null,
                        'selectedProcessingUnit' => null,
                    ]
                )


            </div>

        </section>


        @include('admin.government_ids.partials.application-guide-editor')



        @include('admin.government_ids.partials.office-links-field', [
            'selectedOffices' => collect(),
        ])

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
                Save ID or Credential
            </button>

        </div>

    </form>

</div>

@endsection