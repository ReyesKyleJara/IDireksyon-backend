@extends('admin.layouts.app')

@section('title', 'Add Document | IDireksyon')
@section('page_title', 'Add Document')

@section('content')

<div class="mx-auto max-w-4xl">

    <div class="mb-7">
        <p class="admin-eyebrow mb-2">Documents & Certifications</p>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            Add Document
        </h1>
    </div>

    <form
        method="POST"
        action="{{ route('admin.documents.store') }}"
        class="admin-panel p-6"
    >

        @csrf

        <div class="grid gap-6 sm:grid-cols-2">

            <div class="sm:col-span-2">
                <label for="name" class="mb-2 block text-sm font-medium">
                    Name *
                </label>

                <input
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    class="admin-input"
                    required
                >
            </div>

            <div>
                <label for="level" class="mb-2 block text-sm font-medium">
                    Level
                </label>

                <select id="level" name="level" class="admin-input">
                    <option value="">Select level</option>
                    <option value="Barangay" @selected(old('level') === 'Barangay')>Barangay</option>
                    <option value="Municipal / LGU" @selected(old('level') === 'Municipal / LGU')>Municipal / LGU</option>
                    <option value="National" @selected(old('level') === 'National')>National</option>
                </select>
            </div>

            <div>
                <label for="category" class="mb-2 block text-sm font-medium">
                    Category
                </label>

                <input
                    id="category"
                    name="category"
                    value="{{ old('category') }}"
                    class="admin-input"
                    placeholder="e.g. Civil Registry"
                >
            </div>

            <div>
                <label for="issued_by" class="mb-2 block text-sm font-medium">
                    Issued By
                </label>

                <input
                    id="issued_by"
                    name="issued_by"
                    value="{{ old('issued_by') }}"
                    class="admin-input"
                >
            </div>

            <div>
                <label for="office_location" class="mb-2 block text-sm font-medium">
                    Office Location
                </label>

                <input
                    id="office_location"
                    name="office_location"
                    value="{{ old('office_location') }}"
                    class="admin-input"
                >
            </div>

            @foreach([
                'description' => 'Description / Purpose',
                'eligibility' => 'Eligibility',
                'requirements' => 'Requirements'
            ] as $field => $label)

                <div class="sm:col-span-2">
                    <label for="{{ $field }}" class="mb-2 block text-sm font-medium">
                        {{ $label }}
                    </label>

                    <textarea
                        id="{{ $field }}"
                        name="{{ $field }}"
                        rows="3"
                        class="admin-input"
                    >{{ old($field) }}</textarea>
                </div>

            @endforeach

            <div>
                <label for="fee" class="mb-2 block text-sm font-medium">Fee</label>
                <input id="fee" name="fee" value="{{ old('fee') }}" class="admin-input">
            </div>

            <div>
                <label for="processing_time" class="mb-2 block text-sm font-medium">
                    Processing Time
                </label>
                <input id="processing_time" name="processing_time" value="{{ old('processing_time') }}" class="admin-input">
            </div>

            <div>
                <label for="validity" class="mb-2 block text-sm font-medium">
                    Validity
                </label>
                <input id="validity" name="validity" value="{{ old('validity') }}" class="admin-input">
            </div>

        </div>

        <div class="mt-8 flex justify-end gap-3">

            <a href="{{ route('admin.documents.index') }}" class="admin-secondary">
                Cancel
            </a>

            <button type="submit" class="admin-primary">
                Save Document
            </button>

        </div>

    </form>

</div>

@endsection