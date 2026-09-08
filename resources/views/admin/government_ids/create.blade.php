@extends('admin.layouts.app')

@section('content')

    <h2>Add Government ID</h2>

    <form method="POST" action="{{ route('admin.government-ids.store') }}">

        @csrf

        <label for="name">ID Name</label><br>
        <input type="text" id="name" name="name" required>
        <br><br>

        <label for="agency">Issuing Agency</label><br>
        <input type="text" id="agency" name="agency" required>
        <br><br>

        <label for="purpose">Purpose</label><br>
        <textarea id="purpose" name="purpose"></textarea>
        <br><br>

        <label for="validity">Validity Period</label><br>
        <input type="text" id="validity" name="validity">
        <br><br>

        <label for="description">Description</label><br>
        <textarea id="description" name="description"></textarea>
        <br><br>

        <button type="submit">Save Government ID</button>

    </form>

@endsection