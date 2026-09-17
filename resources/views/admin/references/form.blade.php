@extends('admin.layouts.app')
@section('title', $title.' | IDireksyon Admin Panel')
@section('page_title', $title)
@section('content')
@php
    $value = fn($key,$fallback=null) => is_scalar(old($key,$record->$key ?? $fallback)) ? old($key,$record->$key ?? $fallback) : '';
    $hours = old('hours', $type==='offices' && $record->exists ? $record->hours->map(fn($h)=>['day_of_week'=>$h->day_of_week,'opens_at'=>substr($h->opens_at,0,5),'closes_at'=>substr($h->closes_at,0,5),'notes'=>$h->notes])->all() : []);
    $hours = is_array($hours) ? array_values(array_filter($hours,'is_array')) : [];
@endphp
<div class="mx-auto max-w-5xl">
    <a href="{{ route('admin.references.index',$type) }}" class="text-sm text-slate-500">&larr; {{ $title }}</a>
    <h1 class="mb-6 mt-5 text-3xl font-bold">{{ $record->exists ? $record->name : 'Add entry' }}</h1>
    @if($errors->any())<div role="alert" class="mb-5 rounded-lg bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $record->exists ? route('admin.references.update',[$type,$record->id]) : route('admin.references.store',$type) }}" class="space-y-6" x-data="{ hours: @js($hours) }">
        @csrf @if($record->exists) @method('PUT') @endif
        <section class="admin-panel grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
            <x-admin.field name="name" label="Name" :value="$value('name')" :required="true" maxlength="255" />
            <div><label for="status" class="mb-2 block text-sm font-medium">Status</label><select name="status" id="status" class="admin-input">@foreach(($type==='offices'?['needs_research'=>'Needs research']:[])+['active'=>'Active','inactive'=>'Inactive / archived'] as $key=>$label)<option value="{{ $key }}" @selected($value('status',$type==='offices'?'needs_research':'active')===$key)>{{ $label }}</option>@endforeach</select></div>
            @if(in_array($type,['levels','categories']))<x-admin.field name="slug" label="Short code" :value="$value('slug')" :required="true" maxlength="255" hint="Use lowercase words separated by hyphens, e.g. civil-registry." />@endif
            @if($type==='agencies')<x-admin.field name="abbreviation" label="Abbreviation" :value="$value('abbreviation')" maxlength="40" />@endif
            @if($type==='categories')<div><label for="parent_category_id" class="mb-2 block text-sm font-medium">Parent category</label><select id="parent_category_id" name="parent_category_id" class="admin-input"><option value="">None — top-level category</option>@foreach($categories->where('id','!=',$record->id) as $category)<option value="{{ $category->id }}" @selected($value('parent_category_id')==$category->id)>{{ $category->display_name }}</option>@endforeach</select></div>@endif
            @if($type==='offices')
                @foreach(['agency_id'=>['Agency',$agencies], 'barangay_id'=>['Barangay (if applicable)',$barangays]] as $field=>$config)<div><label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $config[0] }}</label><select id="{{ $field }}" name="{{ $field }}" class="admin-input"><option value="">Unassigned</option>@foreach($config[1] as $option)<option value="{{ $option->id }}" @selected($value($field)==$option->id)>{{ $option->name }}{{ $option->status==='inactive' ? ' (inactive)' : '' }}</option>@endforeach</select></div>@endforeach
                <x-admin.field name="address" label="Street / address" type="textarea" :value="$value('address')" maxlength="5000" class="sm:col-span-2" />
            @endif
            @if(in_array($type,['offices','barangays']))
                <x-admin.field name="municipality" label="Municipality / city" :value="$value('municipality',$type==='barangays'?'Santa Maria':null)" :required="$type==='barangays'" maxlength="255" />
                <x-admin.field name="province" label="Province" :value="$value('province',$type==='barangays'?'Bulacan':null)" :required="$type==='barangays'" maxlength="255" />
            @endif
            @if($type==='offices')
                @foreach(['latitude'=>'Latitude','longitude'=>'Longitude','phone'=>'Contact number','email'=>'Email'] as $field=>$label)<x-admin.field :name="$field" :label="$label" :type="$field==='email'?'email':'text'" :value="$value($field)" />@endforeach
                <x-admin.field name="source_url" label="Official location / schedule source" type="url" :value="$value('source_url')" maxlength="2048" />
                <x-admin.field name="source_checked_at" label="Source checked on" type="date" :value="$errors->any() ? $value('source_checked_at') : $record->source_checked_at?->format('Y-m-d')" />
                <x-admin.field name="notes" label="Location / availability notes" type="textarea" :value="$value('notes')" maxlength="10000" class="sm:col-span-2" />
            @else<x-admin.field name="description" label="Description / research notes" type="textarea" :value="$value('description')" maxlength="10000" class="sm:col-span-2" />@endif
        </section>
        @if($type==='offices')
            <section class="admin-panel space-y-4 p-5 sm:p-7" id="office-hours">
                <h2 class="font-semibold">Office hours</h2><p class="text-sm text-slate-500">Add only confirmed hours. No entry means schedule unknown, not closed. Add separate intervals for breaks; split overnight schedules by day.</p>
                <input type="hidden" :name="'hours_present'" value="1">
                <template x-for="(hour,index) in hours" :key="index"><div class="grid gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-3">
                    <label class="text-sm">Day<select :name="'hours['+index+'][day_of_week]'" x-model="hour.day_of_week" class="admin-input mt-1">@foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day=>$name)<option value="{{ $day }}">{{ $name }}</option>@endforeach</select></label>
                    <label class="text-sm">Opens<input type="time" required :name="'hours['+index+'][opens_at]'" x-model="hour.opens_at" class="admin-input mt-1"></label>
                    <label class="text-sm">Closes<input type="time" required :name="'hours['+index+'][closes_at]'" x-model="hour.closes_at" class="admin-input mt-1"></label>
                    <label class="text-sm sm:col-span-2">Schedule note<input :name="'hours['+index+'][notes]'" x-model="hour.notes" maxlength="255" class="admin-input mt-1"></label>
                    <button type="button" @click="hours.splice(index,1)" class="text-sm text-red-700 underline">Remove interval</button>
                </div></template>
                <button type="button" @click="hours.push({day_of_week:1,opens_at:'',closes_at:'',notes:''})" :disabled="hours.length>=28" class="admin-secondary">Add hours</button>
                @if($record->exists)<p class="text-xs text-slate-500">Office-service links are managed under each ID or document’s “Offices offering this service” section.</p>@endif
            </section>
        @endif
        <div class="flex justify-between gap-3"><a href="{{ route('admin.references.index',$type) }}" class="admin-secondary">Cancel</a><button type="submit" class="admin-primary">Save entry</button></div>
    </form>
    @if($record->exists)<details class="mt-8 rounded-lg border border-red-200 p-5"><summary class="cursor-pointer text-sm font-semibold text-red-700">Delete unused entry</summary><p class="mt-3 text-sm text-slate-500">Prefer setting the status to inactive. Deletion is blocked while linked records exist.</p><form class="mt-3" method="POST" action="{{ route('admin.references.destroy',[$type,$record->id]) }}" onsubmit="return confirm('Permanently delete this unused entry?');">@csrf @method('DELETE')<button class="admin-secondary text-red-700">Delete permanently</button></form></details>@endif
</div>
@endsection
