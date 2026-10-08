@extends('admin.layouts.app')
@section('title', 'Requirements | IDireksyon')
@section('page_title', 'Manage Requirements')
@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('admin.government-ids.requirement-sets.index', $governmentId) }}" class="text-sm text-slate-500">← Back to requirement sets</a>
    <div class="mb-6 mt-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="admin-eyebrow mb-2">{{ $governmentId->name }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $set->display_label }}</h1>
            <p class="mt-2 text-sm text-slate-500">Organize the items an applicant needs to prepare.</p>
        </div>
        <a href="{{ route('admin.government-ids.requirement-sets.groups.create', [$governmentId, $set]) }}" class="admin-primary">Add Group</a>
    </div>
    <div class="space-y-5">
        @forelse($groups as $group)
            <section class="admin-panel p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $group->title ?: 'Group '.$loop->iteration }}</h2>
                        <p class="mt-1 text-sm font-medium text-[#012877]">{{ \App\Models\GovernmentIdRequirementGroup::RULES[$group->rule] }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $group->rule === 'choose_one' ? 'Prepare any one of the following options.' : 'Prepare every item in this group.' }}</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('admin.government-ids.requirement-sets.groups.edit', [$governmentId, $set, $group]) }}" class="admin-secondary">Edit</a>
                        <form method="POST" action="{{ route('admin.government-ids.requirement-sets.groups.destroy', [$governmentId, $set, $group]) }}" onsubmit="return confirm('Delete this group and its requirement items? The referenced IDs and Documents will remain.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="admin-secondary text-red-600">Delete</button>
                        </form>
                    </div>
                </div>
                @if($group->condition_type !== 'always')
                    <div class="mt-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
                        <span class="font-semibold">Only applies when:</span> {{ $group->condition_label }}
                    </div>
                @endif
                <ul class="mt-5 divide-y divide-slate-100">
                    @foreach($group->items as $item)
                        <li class="py-4 first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-medium text-slate-900">{{ $item->display_name }}</h3>
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ \App\Models\GovernmentIdRequirementItem::TYPES[$item->type] }}</span>
                            </div>
                            @if($item->submission_format !== 'not_specified')
                                <p class="mt-2 text-sm text-slate-600">
                                    {{ $item->submission_label }}
                                    @if($item->copies !== null)
                                        · {{ $item->copies }} {{ $item->submission_format === 'original_photocopy' ? ($item->copies === 1 ? 'photocopy' : 'photocopies') : ($item->copies === 1 ? 'copy' : 'copies') }}
                                    @endif
                                </p>
                            @endif
                            @if(filled($item->instructions))
                                <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-600">{{ $item->instructions }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="admin-panel p-8 text-center">
                <h2 class="font-semibold text-slate-900">No requirement groups yet.</h2>
                <p class="mt-2 text-sm text-slate-500">Add an All Required group or a Choose 1 group to start the checklist.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
