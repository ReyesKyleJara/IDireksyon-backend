@extends('admin.layouts.app')
@section('title', 'Audit Logs | IDireksyon')
@section('page_title', 'Audit Logs')
@section('content')
<div class="mx-auto max-w-6xl">
    <h1 class="text-3xl font-bold tracking-tight">Audit Logs</h1><p class="mb-6 mt-2 text-sm text-slate-500">Read-only history of CMS changes from the time logging was enabled.</p>
    <form method="GET" class="admin-panel mb-5 flex gap-3 p-4"><label class="min-w-0 flex-1"><span class="sr-only">Search audit logs</span><input name="q" value="{{ is_string(request('q')) ? request('q') : '' }}" maxlength="255" class="admin-input" placeholder="Search user, record, or section"></label><button class="admin-secondary">Search</button></form>
    <div class="admin-panel overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">CMS change history</caption><thead class="border-b border-slate-200 bg-slate-50"><tr>@foreach(['When', 'User', 'Action', 'Record', 'Fields changed'] as $heading)<th scope="col" class="px-4 py-3 font-semibold">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">
        @forelse($logs as $log)<tr><td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $log->created_at->format('M j, Y g:i a') }}</td><td class="px-4 py-3">{{ $log->user?->name ?? 'Former account' }}</td><td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</td><td class="px-4 py-3">{{ $log->entity_name ?: ucfirst(str_replace('_', ' ', $log->entity_type)) }}<span class="block text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $log->entity_type)) }}</span></td><td class="px-4 py-3 text-slate-500">{{ collect($log->changed_fields)->map(fn ($field) => ucfirst(str_replace('_', ' ', $field)))->implode(', ') ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="p-8 text-center text-slate-500">No changes recorded yet.</td></tr>@endforelse
    </tbody></table></div><div class="mt-5">{{ $logs->links() }}</div>
</div>
@endsection
