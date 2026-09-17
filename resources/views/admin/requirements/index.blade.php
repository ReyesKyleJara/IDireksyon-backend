@extends('admin.layouts.app')

@section('content')

    <div class="flex items-center justify-between mb-8">

        <div>
            <h2 class="text-2xl font-semibold text-gray-900">
                Requirements
            </h2>

            <p class="text-gray-500 mt-1">
                Manage reusable requirements used by government IDs.
            </p>
        </div>

        <a
            href="{{ route('admin.requirements.create') }}"
            class="bg-blue-800 text-white px-4 py-2.5 rounded-lg font-medium hover:bg-blue-900"
        >
            + Add Requirement
        </a>

    </div>

    <div class="mb-5">
        <p class="text-sm text-gray-500">
            {{ $requirements->count() }}
            {{ $requirements->count() === 1 ? 'requirement' : 'requirements' }}
        </p>
    </div>

    @if ($requirements->isEmpty())

        <div class="bg-white rounded-2xl shadow-sm p-10 text-center">

            <p class="text-gray-500">
                No requirements have been added yet.
            </p>

            <a
                href="{{ route('admin.requirements.create') }}"
                class="inline-block mt-4 text-blue-700 font-medium hover:underline"
            >
                Add your first requirement
            </a>

        </div>

    @else

        <div class="space-y-3">

            @foreach ($requirements as $requirement)

                <a
                    href="{{ route('admin.requirements.show', $requirement->id) }}"
                    class="block bg-white rounded-2xl shadow-sm p-6 hover:shadow-md hover:bg-gray-50 transition"
                >

                    <div class="flex items-center justify-between gap-6">

                        <div class="min-w-0">

                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ $requirement->name }}
                            </h3>

                            @if ($requirement->description)

                                <p class="text-sm text-gray-500 mt-1">
                                    {{ $requirement->description }}
                                </p>

                            @else

                                <p class="text-sm text-gray-400 mt-1">
                                    No description provided.
                                </p>

                            @endif

                        </div>

                        <div class="text-gray-400 text-xl">
                            →
                        </div>

                    </div>

                </a>

            @endforeach

        </div>

    @endif

@endsection