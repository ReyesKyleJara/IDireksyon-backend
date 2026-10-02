@extends('admin.layouts.app')
@section('title', 'Offices | IDireksyon')
@section('page_title', 'Offices')
@section('content')
<div class="mx-auto max-w-7xl">
    <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="admin-eyebrow mb-2">Office Directory</p>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Offices</h1>
            <p class="mt-2 text-sm text-slate-500">Research branches serving Santa Maria and nearby communities. Save incomplete findings as drafts.</p>
        </div>
        <a href="{{ route('admin.offices.create') }}" class="admin-primary">Add Office</a>
    </div>
    <form method="GET" action="{{ route('admin.offices.index') }}" class="admin-panel mb-5 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="office-search" class="mb-2 block text-sm font-medium">Search</label>
            <input id="office-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Office, agency, or location" class="admin-input">
        </div>
        <div>
            <label for="agency-filter" class="mb-2 block text-sm font-medium">Agency</label>
            <select id="agency-filter" name="agency_id" class="admin-input">
                <option value="">All agencies</option>
                @foreach($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected(($filters['agency_id'] ?? '') == $agency->id)>{{ $agency->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status-filter" class="mb-2 block text-sm font-medium">Status</label>
            <select id="status-filter" name="status" class="admin-input">
                <option value="">All statuses</option>
                <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="flex items-end gap-3">
            <button class="admin-primary" type="submit">Search</button>
            <a href="{{ route('admin.offices.index') }}" class="admin-secondary">Clear</a>
        </div>
    </form>
    <div class="admin-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach(['Office', 'Agency', 'Location', 'Status', 'Actions'] as $heading)
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($offices as $office)
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-900">{{ $office->name }}</td>
                            <td class="px-5 py-4 text-sm text-slate-600">{{ $office->agency?->name ?? 'Not yet assigned' }}</td>
                            <td class="px-5 py-4 text-sm text-slate-600">{{ collect([$office->municipality, $office->province])->filter()->implode(', ') ?: 'Not yet researched' }}</td>
                            <td class="px-5 py-4 text-sm"><span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ ucfirst($office->status) }}</span></td>
                            <td class="px-5 py-4"><a href="{{ route('admin.offices.edit', $office) }}" class="admin-secondary" aria-label="Edit {{ $office->name }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-16 text-center">
                            <p class="font-semibold text-slate-800">{{ array_filter($filters) ? 'No matching offices found.' : 'No offices yet.' }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ array_filter($filters) ? 'Try another search or clear the filters.' : 'Add a branch when you have its name. Other research details can follow.' }}</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $offices->links() }}</div>
</div>
@endsection
