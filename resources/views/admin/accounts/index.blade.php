@extends('admin.layouts.app')
@section('title', 'Admin Users | IDireksyon Admin Panel')
@section('page_title', 'Admin Users')
@section('content')
<div class="mx-auto max-w-6xl">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div><p class="admin-eyebrow mb-2">Administration</p><h1 class="text-3xl font-bold tracking-tight">Admin Users</h1><p class="mt-2 text-sm text-slate-500">Manage Super Admin and researcher accounts.</p></div>
        <a href="{{ route('admin.accounts.create') }}" class="admin-primary">Add account</a>
    </div>
    @if($errors->any())<p role="alert" class="mb-4 text-sm text-red-700">{{ $errors->first() }}</p>@endif
    <form method="GET" class="admin-panel mb-5 flex flex-wrap gap-3 p-4">
        <label class="min-w-0 flex-1"><span class="sr-only">Search accounts</span><input class="admin-input" name="q" value="{{ is_string(request('q')) ? request('q') : '' }}" maxlength="255" placeholder="Search name or username"></label>
        <label><span class="sr-only">Role</span><select name="role" class="admin-input"><option value="">All roles</option>@foreach($roles as $key=>$label)<option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>@endforeach</select></label>
        <label><span class="sr-only">Status</span><select name="status" class="admin-input"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></label>
        <button class="admin-secondary">Search</button><a href="{{ route('admin.accounts.index') }}" class="admin-secondary">Clear</a>
    </form>
    <div class="admin-panel overflow-x-auto">
        <table class="w-full text-left text-sm"><caption class="sr-only">CMS administrator accounts</caption>
            <thead class="border-b border-slate-200 bg-slate-50 text-slate-600"><tr>@foreach(['Name', 'Username', 'Role', 'Status', 'Action'] as $heading)<th scope="col" class="px-5 py-3 font-semibold">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($accounts as $account)
                    <tr class="hover:bg-slate-50"><th scope="row" class="px-5 py-4 font-medium">{{ $account->name }} @if($account->id === auth()->id())<span class="text-xs text-slate-500">(you)</span>@endif</th><td class="px-5 py-4 text-slate-500">{{ $account->username }}</td><td class="px-5 py-4">{{ $roles[$account->role] }}</td><td class="px-5 py-4">{{ $account->is_active ? 'Active' : 'Inactive' }}</td><td class="px-5 py-4"><a href="{{ route('admin.accounts.edit', $account) }}" class="font-medium text-[#012877] hover:underline">Edit<span class="sr-only"> {{ $account->name }}</span></a></td></tr>
                @empty<tr><td colspan="5" class="p-8 text-center text-slate-500">No matching accounts.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $accounts->links() }}</div>
</div>
@endsection
