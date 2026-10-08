@extends('admin.layouts.app')
@section('title', ($set->exists ? 'Edit Requirement Set' : 'Add Requirement Set').' | IDireksyon')
@section('page_title', $set->exists ? 'Edit Requirement Set' : 'Add Requirement Set')
@section('content')
@php
    $value = function ($field) use ($set) {
        $input = old($field, $set->getAttribute($field));
        return is_scalar($input) ? (string) $input : '';
    };
    $initial = ['application' => $value('application_type'), 'applicant' => $value('applicant_type')];
@endphp
<div class="mx-auto max-w-2xl">
    <a href="{{ route('admin.government-ids.requirement-sets.index', $governmentId) }}" class="text-sm text-slate-500">← Back to requirement sets</a>
    <div class="mb-7 mt-5">
        <p class="admin-eyebrow mb-2">{{ $governmentId->name }}</p>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $set->exists ? 'Edit Requirement Set' : 'Add Requirement Set' }}</h1>
        <p class="mt-2 text-sm text-slate-500">Choose the broad situation this checklist applies to.</p>
    </div>
    @if($errors->any())
        <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="mb-2 font-semibold">Please check the following:</p>
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST"
          action="{{ $set->exists ? route('admin.government-ids.requirement-sets.update', [$governmentId, $set]) : route('admin.government-ids.requirement-sets.store', $governmentId) }}"
          x-data="{{ Illuminate\Support\Js::from($initial) }}"
          class="space-y-6">
        @csrf
        @if($set->exists)
            @method('PUT')
        @endif
        <section class="admin-panel space-y-5 p-6">
            <div>
                <label for="application_type" class="mb-2 block text-sm font-medium">Application Type *</label>
                <select id="application_type" name="application_type" x-model="application" class="admin-input" required>
                    <option value="">Select application type</option>
                    @foreach($applicationTypes as $key => $label)
                        <option value="{{ $key }}" @selected($value('application_type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('application_type')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div x-show="application === 'custom'" x-cloak>
                <label for="application_type_custom" class="mb-2 block text-sm font-medium">Custom Application Name *</label>
                <input id="application_type_custom" name="application_type_custom" value="{{ $value('application_type_custom') }}" class="admin-input" maxlength="255" :disabled="application !== 'custom'" :required="application === 'custom'">
                @error('application_type_custom')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="applicant_type" class="mb-2 block text-sm font-medium">Applicant Type *</label>
                <select id="applicant_type" name="applicant_type" x-model="applicant" class="admin-input" required>
                    <option value="">Select applicant type</option>
                    @foreach($applicantTypes as $key => $label)
                        <option value="{{ $key }}" @selected($value('applicant_type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('applicant_type')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <p x-show="applicant === 'adult'" x-cloak class="text-sm text-slate-600">Age requirement: 18 and above (automatic).</p>
            <p x-show="applicant === 'minor'" x-cloak class="text-sm text-slate-600">Age requirement: below 18 (automatic).</p>
            <p x-show="applicant === 'all'" x-cloak class="text-sm text-slate-600">No age restriction for this set.</p>
            <div x-show="applicant === 'custom'" x-cloak class="space-y-5">
                <div>
                    <label for="applicant_type_custom" class="mb-2 block text-sm font-medium">Custom Applicant Label <span class="font-normal text-slate-500">(optional)</span></label>
                    <input id="applicant_type_custom" name="applicant_type_custom" value="{{ $value('applicant_type_custom') }}" class="admin-input" maxlength="255" :disabled="applicant !== 'custom'">
                    @error('applicant_type_custom')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="min_age" class="mb-2 block text-sm font-medium">Minimum Age</label>
                        <input type="number" id="min_age" name="min_age" value="{{ $value('min_age') }}" min="0" max="65535" step="1" class="admin-input" :disabled="applicant !== 'custom'">
                        @error('min_age')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="max_age" class="mb-2 block text-sm font-medium">Maximum Age</label>
                        <input type="number" id="max_age" name="max_age" value="{{ $value('max_age') }}" min="0" max="65535" step="1" class="admin-input" :disabled="applicant !== 'custom'">
                        @error('max_age')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="text-sm text-slate-500">Use one age limit or both for a range. Leave both blank for no age restriction. Limits include the ages entered.</p>
            </div>
        </section>
        <p class="text-sm text-slate-500">The set name is generated from your selections. These choices select a checklist; the ID’s eligibility rules still apply.</p>
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.government-ids.requirement-sets.index', $governmentId) }}" class="admin-secondary">Cancel</a>
            <button type="submit" class="admin-primary">{{ $set->exists ? 'Save Changes' : 'Save Requirement Set' }}</button>
        </div>
    </form>
</div>
@endsection
