@extends('admin.layouts.app')
@section('title', 'Change Password | IDireksyon')
@section('page_title', 'Change Password')
@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="mb-6 text-3xl font-bold tracking-tight">Change Password</h1>
    @if(session('status') === 'password-updated')<p role="status" class="mb-5 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800">Password changed.</p>@endif
    @if($errors->updatePassword->any())<div role="alert" class="mb-5 rounded-lg bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->updatePassword->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="admin-panel space-y-5 p-5 sm:p-7">
            @foreach(['current_password' => 'Current Password', 'password' => 'New Password', 'password_confirmation' => 'Confirm New Password'] as $field=>$label)
                <div><label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="password" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" required class="admin-input"></div>
            @endforeach
        </div>
        <button class="admin-primary">Change Password</button>
    </form>
</div>
@endsection
