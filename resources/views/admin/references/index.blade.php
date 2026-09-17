@extends('admin.layouts.app')
@section('title', $title.' | IDireksyon Admin Panel')
@section('page_title', $title)
@section('content')
<div class="mx-auto max-w-6xl">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4"><div><p class="admin-eyebrow mb-2">Directory foundation</p><h1 class="text-3xl font-bold">{{ $title }}</h1><p class="mt-2 text-sm text-slate-500">Maintain reusable reference data and service locations.</p></div><a href="{{ route('admin.references.create',$type) }}" class="admin-primary">Add entry</a></div>
    <nav aria-label="Reference directories" class="mb-5 flex flex-wrap gap-2">@foreach($tabs as $key=>$tab)<a href="{{ route('admin.references.index',$key) }}" class="admin-secondary {{ $key===$type ? 'border-blue-800 text-blue-800' : '' }}" @if($key===$type) aria-current="page" @endif>{{ $tab[1] }}</a>@endforeach</nav>
    @if($type==='categories')<p class="mb-5 text-sm text-slate-500">Categories describe subject matter. Levels describe issuing jurisdiction; record types describe the kind of document. These are IDireksyon’s editorial groupings.</p>@endif
    @if($errors->any())<div role="alert" class="mb-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <form class="admin-panel mb-5 flex flex-wrap gap-3 p-4" method="GET">
        <label class="min-w-0 flex-1"><span class="sr-only">Search {{ $title }}</span><input name="q" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="Search names" class="admin-input" maxlength="255"></label>
        <label><span class="sr-only">Status</span><select name="status" class="admin-input"><option value="">All statuses</option>@foreach(['active'=>'Active','inactive'=>'Inactive']+($type==='offices'?['needs_research'=>'Needs research']:[]) as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></label>
        <button class="admin-secondary">Search / filter</button><a href="{{ route('admin.references.index',$type) }}" class="admin-secondary">Clear</a>
    </form>
    <p class="mb-3 text-sm text-slate-500">{{ $records->total() }} entries</p>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">{{ $title }} directory</caption>
            <thead class="border-b border-slate-200 bg-slate-50 text-slate-700">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold">{{ ['levels'=>'Level', 'agencies'=>'Agency', 'barangays'=>'Barangay', 'offices'=>'Office'][$type] ?? 'Name' }}</th>
                    @if($type === 'agencies')
                        <th scope="col" class="px-4 py-3 font-semibold">Acronym</th>
                    @elseif($type === 'categories')
                        <th scope="col" class="px-4 py-3 font-semibold">Parent Category</th>
                    @elseif($type === 'offices')
                        <th scope="col" class="px-4 py-3 font-semibold">Agency</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Barangay</th>
                    @endif
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($records as $record)
                    <tr class="hover:bg-slate-50">
                        <th scope="row" class="break-words px-4 py-3 font-medium text-slate-900">{{ $record->name }}</th>
                        @if($type === 'agencies')
                            <td class="px-4 py-3 text-slate-600">{{ $record->abbreviation ?: '—' }}</td>
                        @elseif($type === 'categories')
                            <td class="px-4 py-3 text-slate-600">{{ $record->parentCategory?->name ?? '—' }}</td>
                        @elseif($type === 'offices')
                            <td class="px-4 py-3 text-slate-600">{{ $record->agency?->name ?? 'Agency unassigned' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $record->barangay?->name ?? 'Unassigned' }}</td>
                        @endif
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.references.edit', [$type, $record->id]) }}" class="inline-flex py-1 font-medium text-[#012877] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#012877]">Edit<span class="sr-only"> {{ $record->name }}</span></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $type === 'offices' ? 4 : (in_array($type, ['agencies', 'categories']) ? 3 : 2) }}" class="px-4 py-10 text-center text-slate-500">No matching entries.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $records->links() }}</div>
</div>
@endsection
