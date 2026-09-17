@extends('admin.layouts.app')
@section('title', $plural.' | IDireksyon Admin Panel')
@section('page_title', $plural)
@section('content')
<div class="mx-auto max-w-7xl">
    <div class="mb-7 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="admin-eyebrow mb-2">Content directory</p>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $plural }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{{ $isDocument ? 'Manage supporting documents, certificates, and their request information.' : 'Manage government-issued IDs and their application information.' }}</p>
        </div>
        <a href="{{ route('admin.'.$resource.'.create') }}" class="admin-primary shrink-0">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" /></svg>
            Add {{ $label }}
        </a>
    </div>
    <div class="admin-panel mb-5 flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.'.$resource.'.index') }}" class="flex w-full items-center gap-3">
            <label class="min-w-0 flex-1"><span class="sr-only">Search {{ strtolower($plural) }}</span><input type="search" name="q" value="{{ $search }}" maxlength="255" placeholder="Search names or agencies" class="admin-input"></label>
            <button type="submit" class="admin-secondary">Search</button>
            @if(request()->hasAny(['q','level_id','category_id','availability_status','research_status']))<a class="text-sm text-[#012877] underline" href="{{ route('admin.'.$resource.'.index') }}">Clear</a>@endif
        </form>
        <p class="shrink-0 text-sm text-slate-500">{{ $records->total() }} entries</p>
    </div>
    @if($records->isEmpty())
        <div class="admin-panel px-6 py-16 text-center">
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-xl bg-slate-50 text-[#012877]"><x-admin.catalog-icon :document="$isDocument" class="h-8 w-8" /></div>
            <h2 class="text-lg font-semibold">{{ ($search !== '' || request('category_id') || request('level_id') || request('availability_status') || request('research_status')) ? 'No matching entries' : 'No '.strtolower($plural).' added yet' }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">{{ $search !== '' ? 'Try another name or issuing agency, or clear your search.' : 'Start with the information you have researched. Optional fields can stay empty until sources are available.' }}</p>
            @if($search === '')<a href="{{ route('admin.'.$resource.'.create') }}" class="admin-primary mt-6">Add {{ $label }}</a>@endif
        </div>
    @else
        <div class="admin-panel overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">{{ $plural }} directory</caption>
                <thead class="border-b border-slate-200 bg-slate-50 text-slate-700"><tr>@foreach(['Name', 'Level', 'Category', 'Agency', 'Research Status', 'Last Updated', 'Actions'] as $heading)<th scope="col" class="px-4 py-3 font-semibold">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($records as $record)
                        <tr class="hover:bg-slate-50">
                            <th scope="row" class="px-4 py-4 font-medium"><a href="{{ route('admin.'.$resource.'.show', $record) }}" class="text-[#012877] hover:underline">{{ $record->name }}</a></th>
                            <td class="px-4 py-4 text-slate-600">{{ $record->level?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ $record->category?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ $record->agency_name ?: 'Unassigned' }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ $researchStages[$record->research_status] ?? 'Needs research' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ $record->last_updated?->format('M j, Y') ?? 'Not recorded' }}</td>
                            <td class="px-4 py-4"><div class="flex items-center gap-3">
                                <a href="{{ route('admin.'.$resource.'.show', $record) }}" class="font-medium text-[#012877] hover:underline" aria-label="View {{ $record->name }}">View</a>
                                <a href="{{ route('admin.'.$resource.'.edit', $record) }}" class="font-medium text-[#012877] hover:underline" aria-label="Edit {{ $record->name }}">Edit</a>
                                <form method="POST" action="{{ route('admin.'.$resource.'.destroy', $record) }}" onsubmit="return confirm('Permanently delete this entry? This cannot be undone.');">@csrf @method('DELETE')<button type="submit" class="text-red-700 hover:underline" aria-label="Delete {{ $record->name }}">Delete</button></form>
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $records->links() }}</div>
    @endif
</div>
@endsection
