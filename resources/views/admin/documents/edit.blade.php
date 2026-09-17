@extends('admin.layouts.app')

@section('title', 'Edit Document | IDireksyon')
@section('page_title', 'Edit Document')

@section('content')

<div class="mx-auto max-w-4xl">

    <div class="mb-7">

        <p class="admin-eyebrow mb-2">
            Documents & Certifications
        </p>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            Edit Document
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            Update the information for {{ $document->name }}.
        </p>

    </div>


    <form
        method="POST"
        action="{{ route('admin.documents.update', $document) }}"
        class="admin-panel p-6"
    >

        @csrf
        @method('PUT')


        <div class="grid gap-6 sm:grid-cols-2">

            {{-- NAME --}}
            <div class="sm:col-span-2">

                <label for="name" class="mb-2 block text-sm font-medium">
                    Name *
                </label>

                <input
                    id="name"
                    name="name"
                    value="{{ old('name', $document->name) }}"
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

                <label for="level" class="mb-2 block text-sm font-medium">
                    Level
                </label>

                <select
                    id="level"
                    name="level"
                    class="admin-input"
                >
                    <option value="">Select level</option>

                    <option
                        value="Barangay"
                        @selected(old('level', $document->level) === 'Barangay')
                    >
                        Barangay
                    </option>

                    <option
                        value="Municipal / LGU"
                        @selected(old('level', $document->level) === 'Municipal / LGU')
                    >
                        Municipal / LGU
                    </option>

                    <option
                        value="National"
                        @selected(old('level', $document->level) === 'National')
                    >
                        National
                    </option>
                </select>

            </div>


            {{-- CATEGORY --}}
            <div>

                <label for="category" class="mb-2 block text-sm font-medium">
                    Category
                </label>

                <input
                    id="category"
                    name="category"
                    value="{{ old('category', $document->category) }}"
                    class="admin-input"
                    placeholder="e.g. Civil Registry"
                >

            </div>


            {{-- ISSUED BY --}}
            <div>

                <label for="issued_by" class="mb-2 block text-sm font-medium">
                    Issued By
                </label>

                <input
                    id="issued_by"
                    name="issued_by"
                    value="{{ old('issued_by', $document->issued_by) }}"
                    class="admin-input"
                >

            </div>


            {{-- OFFICE LOCATION --}}
            <div>

                <label for="office_location" class="mb-2 block text-sm font-medium">
                    Office Location
                </label>

                <input
                    id="office_location"
                    name="office_location"
                    value="{{ old('office_location', $document->office_location) }}"
                    class="admin-input"
                >

            </div>


            {{-- DESCRIPTION --}}
            <div class="sm:col-span-2">

                <label for="description" class="mb-2 block text-sm font-medium">
                    Description / Purpose
                </label>

                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="admin-input"
                >{{ old('description', $document->description) }}</textarea>

            </div>


            {{-- ELIGIBILITY --}}
            <div class="sm:col-span-2">

                <label for="eligibility" class="mb-2 block text-sm font-medium">
                    Eligibility
                </label>

                <textarea
                    id="eligibility"
                    name="eligibility"
                    rows="3"
                    class="admin-input"
                >{{ old('eligibility', $document->eligibility) }}</textarea>

            </div>


            {{-- REQUIREMENTS --}}
            <div class="sm:col-span-2">

                <label for="requirements" class="mb-2 block text-sm font-medium">
                    Requirements
                </label>

                <textarea
                    id="requirements"
                    name="requirements"
                    rows="4"
                    class="admin-input"
                >{{ old('requirements', $document->requirements) }}</textarea>

            </div>


            {{-- FEE --}}
            <div>

                <label for="fee" class="mb-2 block text-sm font-medium">
                    Fee
                </label>

                <input
                    id="fee"
                    name="fee"
                    value="{{ old('fee', $document->fee) }}"
                    class="admin-input"
                >

            </div>


            {{-- PROCESSING TIME --}}
            <div>

                <label for="processing_time" class="mb-2 block text-sm font-medium">
                    Processing Time
                </label>

                <input
                    id="processing_time"
                    name="processing_time"
                    value="{{ old('processing_time', $document->processing_time) }}"
                    class="admin-input"
                >

            </div>


            {{-- VALIDITY --}}
            <div>

                <label for="validity" class="mb-2 block text-sm font-medium">
                    Validity
                </label>

                <input
                    id="validity"
                    name="validity"
                    value="{{ old('validity', $document->validity) }}"
                    class="admin-input"
                >

            </div>

        </div>


        <div class="mt-8 flex justify-end gap-3">

            <a
                href="{{ route('admin.documents.index') }}"
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