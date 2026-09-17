@extends('admin.layouts.app')

@section('content')

    <h2 class="text-2xl font-semibold mb-2">
        Dashboard
    </h2>

    <p class="text-gray-600 mb-6">
        Welcome to the IDireksyon Admin Panel.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-sm text-gray-500">
                Total Government IDs
            </p>

            <p class="text-3xl font-bold mt-2">
                {{ $totalGovernmentIds }}
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-sm text-gray-500">
                Added in the Last 30 Days
            </p>

            <p class="text-3xl font-bold mt-2">
                {{ $recentlyAdded }}
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-sm text-gray-500">
                Needs Verification
            </p>

            <p class="text-3xl font-bold mt-2">
                {{ $needsVerification }}
            </p>
        </div>

    </div>

@endsection