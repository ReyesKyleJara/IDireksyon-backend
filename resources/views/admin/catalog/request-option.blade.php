@extends('admin.layouts.app')
@section('title', ($path ? 'Edit Request Method' : 'Add Request Method').' | IDireksyon')
@section('page_title', $path ? 'Edit Request Method' : 'Add Request Method')
@section('content')
@include('admin.government_ids.partials.editor-styles')
@php
    $wizardRoutes = $recordRoutes.'.'.($wizardContext === 'create' ? 'wizard' : 'edit-wizard');
    $returnUrl = route($wizardRoutes.'.show', [$governmentId, 'channels', 'path' => $path?->id]);
@endphp
<div class="id-editor mx-auto max-w-3xl space-y-5">
    <div><h1 class="text-2xl font-semibold">{{ $path ? 'Edit Request Method' : 'Add Request Method' }}</h1><p class="mt-1 text-sm text-slate-500">{{ $governmentId->name }}</p></div>
    @include('admin.catalog.partials.request-option-form')
</div>
@endsection
