@extends('admin.layouts.app')
@section('title', 'Admin Users | IDireksyon Admin Panel')
@section('page_title', 'Admin Users')
@section('content')
@php($value = fn ($key, $fallback = '') => is_scalar(old($key, $account->$key ?? $fallback)) ? old($key, $account->$key ?? $fallback) : '')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('admin.accounts.index') }}" class="text-sm text-slate-500">&larr; Admin Users</a>
    <h1 class="mb-6 mt-5 text-3xl font-bold">{{ $account->exists ? 'Edit account' : 'Add account' }}</h1>
    @if($errors->any())<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $account->exists ? route('admin.accounts.update', $account) : route('admin.accounts.store') }}" class="space-y-6" @if($account->exists) onsubmit="return this.elements.is_active.value !== '0' || confirm('Deactivate this account? They will lose CMS access.');" @endif>
        @csrf @if($account->exists) @method('PUT') @endif
        <section class="admin-panel grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
            <x-admin.field name="name" label="Name" :value="$value('name')" :required="true" maxlength="255" />
            <x-admin.field name="username" label="Username" :value="$value('username')" :required="true" maxlength="80" hint="Letters, numbers, dots, hyphens, and underscores." />
            <x-admin.field name="email" label="Contact email" type="email" :value="$value('email')" :required="true" maxlength="255" />
            <div><label for="role" class="mb-2 block text-sm font-medium">Role *</label><select name="role" id="role" required class="admin-input">@foreach($roles as $key=>$label)<option value="{{ $key }}" @selected($value('role', 'researcher') === $key)>{{ $label }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-500">Researchers manage content. Super Admins also manage accounts.</p></div>
            <div><label for="is_active" class="mb-2 block text-sm font-medium">Status *</label><select name="is_active" id="is_active" required class="admin-input"><option value="1" @selected((string)$value('is_active', 1) === '1')>Active</option><option value="0" @selected((string)$value('is_active', 1) === '0')>Inactive</option></select></div>
            @if($account->id === auth()->id())<p class="text-sm text-slate-500 sm:col-span-2">Another Super Admin must change your own role or deactivate your account.</p>@endif
            <div><label for="password" class="mb-2 block text-sm font-medium">{{ $account->exists ? 'New password (optional)' : 'Password *' }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" @required(!$account->exists) class="admin-input"><p class="mt-2 text-xs text-slate-500">At least 12 characters. {{ $account->exists ? 'Leave blank to keep the existing password.' : '' }}</p></div>
            <div><label for="password_confirmation" class="mb-2 block text-sm font-medium">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!$account->exists) class="admin-input"></div>
        </section>
        <div class="flex justify-between gap-3"><a href="{{ route('admin.accounts.index') }}" class="admin-secondary">Cancel</a><button class="admin-primary">{{ $account->exists ? 'Save changes' : 'Create account' }}</button></div>
    </form>
</div>
@endsection
