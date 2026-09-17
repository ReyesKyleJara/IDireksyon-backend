@extends('admin.layouts.app')

@section('content')

    {{-- Back button --}}
    <div class="mb-6">
        <a
            href="{{ route('admin.requirements.index') }}"
            class="text-sm text-blue-700 hover:underline"
        >
            ← Requirements
        </a>
    </div>

    {{-- Page header --}}
    <div class="mb-6">
        <p class="text-sm text-gray-500 mb-2">
            Requirements
        </p>

        <h2 class="text-3xl font-bold text-gray-900">
            Add Requirement
        </h2>

        <p class="text-gray-500 mt-2">
            Add a requirement that can be used by one or more government IDs.
        </p>
    </div>

    {{-- Form --}}
    <form
        method="POST"
        action="{{ route('admin.requirements.store') }}"
    >

        @csrf

        <div class="bg-white rounded-2xl shadow-sm p-8 mb-6">

            <div class="mb-6">

                <label
                    for="name"
                    class="block text-sm font-medium text-gray-700 mb-2"
                >
                    Requirement Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    placeholder="e.g. Birth Certificate"
                    class="w-full rounded-lg border-gray-300 focus:border-blue-700 focus:ring-blue-700"
                >

                @error('name')
                    <p class="text-sm text-red-600 mt-2">
                        {{ $message }}
                    </p>
                @enderror

            </div>

            <div>

                <label
                    for="description"
                    class="block text-sm font-medium text-gray-700 mb-2"
                >
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    placeholder="Add details about this requirement..."
                    class="w-full rounded-lg border-gray-300 focus:border-blue-700 focus:ring-blue-700"
                >{{ old('description') }}</textarea>

                @error('description')
                    <p class="text-sm text-red-600 mt-2">
                        {{ $message }}
                    </p>
                @enderror

            </div>

        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3">

            <a
                href="{{ route('admin.requirements.index') }}"
                class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="bg-blue-800 text-white px-5 py-2.5 rounded-lg font-medium hover:bg-blue-900"
            >
                Save Requirement
            </button>

        </div>

    </form>

@endsection