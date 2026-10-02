@extends('admin.layouts.app')
@section('title', ($office->exists ? 'Edit Office' : 'Add Office').' | IDireksyon')
@section('page_title', $office->exists ? 'Edit Office' : 'Add Office')
@section('content')
@php
    $value = function ($field) use ($office) {
        $input = old($field, $office->getAttribute($field));
        return is_scalar($input) ? (string) $input : '';
    };
@endphp
<div class="mx-auto max-w-4xl">
    <a href="{{ route('admin.offices.index') }}" class="text-sm text-slate-500">← Back to offices</a>
    <div class="mb-7 mt-5">
        <p class="admin-eyebrow mb-2">Office Research</p>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $office->exists ? 'Edit Office' : 'Add Office' }}</h1>
        <p class="mt-2 text-sm text-slate-500">Only the branch name is required to begin. Draft and inactive offices are hidden from residents.</p>
    </div>
    @if($errors->any())
        <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="mb-2 font-semibold">Please check the following:</p>
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ $office->exists ? route('admin.offices.update', $office) : route('admin.offices.store') }}" class="space-y-6">
        @csrf
        @if($office->exists) @method('PUT') @endif
        <section class="admin-panel p-6">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Office Details</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field name="name" label="Office / Branch Name" :value="$value('name')" :required="true" maxlength="255" class="sm:col-span-2" />
                <div>
                    <label for="agency_id" class="mb-2 block text-sm font-medium">Agency</label>
                    <select id="agency_id" name="agency_id" class="admin-input">
                        <option value="">Not yet assigned</option>
                        @foreach($agencies as $agency)
                            <option value="{{ $agency->id }}" @selected($value('agency_id') === (string) $agency->id)>{{ $agency->name }}</option>
                        @endforeach
                    </select>
                    @error('agency_id')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="status" class="mb-2 block text-sm font-medium">Status</label>
                    <select id="status" name="status" class="admin-input">
                        <option value="draft" @selected($value('status') === 'draft')>Draft — research in progress</option>
                        <option value="inactive" @selected($value('status') === 'inactive')>Inactive — no longer in use</option>
                    </select>
                    @error('status')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>
        <section class="admin-panel p-6">
            <h2 class="text-lg font-semibold text-slate-900">Location Research</h2>
            <p class="mb-5 mt-1 text-sm text-slate-500">Record the address you found. Saving an address does not mark the location as verified.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.field name="address" label="Street / Building Address" type="textarea" :value="$value('address')" maxlength="5000" class="sm:col-span-2" />
                <x-admin.field name="municipality" label="City / Municipality" :value="$value('municipality')" maxlength="255" />
                <x-admin.field name="province" label="Province" :value="$value('province')" maxlength="255" />
            </div>
        </section>
        @include('admin.offices.partials.schedule-fields')
        <section class="admin-panel p-6">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Research Source & Notes</h2>
            <div class="space-y-5">
                <x-admin.field name="source_url" label="Official Source Link" type="url" :value="$value('source_url')" maxlength="2048" hint="Link to official office or schedule information when available." />
                <x-admin.field name="notes" label="Research Notes" type="textarea" :value="$value('notes')" maxlength="10000" hint="Record findings, uncertainties, and details that still need checking." />
            </div>
        </section>
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.offices.index') }}" class="admin-secondary">Cancel</a>
            <button type="submit" class="admin-primary">{{ $office->exists ? 'Save Changes' : 'Save Draft' }}</button>
        </div>
    </form>
</div>
@endsection
