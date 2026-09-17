@extends('admin.layouts.app')

@section('title', 'Dashboard | IDireksyon')

@section('page_title', 'Dashboard')

@section('content')
    <div class="mx-auto max-w-7xl">

        <div class="mb-8">
            <p class="admin-eyebrow mb-2">Overview</p>

            <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                Dashboard
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Manage the content used by IDireksyon.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

            <div class="admin-panel p-6">
                <p class="text-sm font-medium text-slate-500">
                    Government IDs
                </p>

                <p class="mt-2 text-3xl font-bold text-slate-900">
                    {{ $governmentIdCount }}
                </p>
            </div>

        </div>

    </div>
@endsection