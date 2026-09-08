@extends('admin.layouts.app')

@section('content')

    <h2>Edit Government ID</h2>

    <form method="POST" action="{{ route('admin.government-ids.update', $governmentId->id) }}">

        @csrf
        @method('PUT')

        <label for="name">ID Name</label><br>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ $governmentId->name }}"
            required
        >
        <br><br>

        <label for="agency">Issuing Agency</label><br>
        <input
            type="text"
            id="agency"
            name="agency"
            value="{{ $governmentId->agency }}"
            required
        >
        <br><br>

        <label for="purpose">Purpose</label><br>
        <textarea id="purpose" name="purpose">{{ $governmentId->purpose }}</textarea>
        <br><br>

        <label for="validity">Validity Period</label><br>
        <input
            type="text"
            id="validity"
            name="validity"
            value="{{ $governmentId->validity }}"
        >
        <br><br>

        <label for="description">Description</label><br>
        <textarea id="description" name="description">{{ $governmentId->description }}</textarea>
        <br><br>

        <button type="submit">Update Government ID</button>

    </form>

@endsection