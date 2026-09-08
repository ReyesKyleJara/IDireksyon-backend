@extends('admin.layouts.app')

@section('content')

    <h2>Government IDs</h2>

    <a href="{{ route('admin.government-ids.create') }}">
        Add Government ID
    </a>

    @if ($governmentIds->isEmpty())
        <p>No government IDs have been added yet.</p>
    @else
        <ul>
            @foreach ($governmentIds as $governmentId)
                <li>
                    {{ $governmentId->name }}
                    - {{ $governmentId->agency }}

                    <a href="{{ route('admin.government-ids.edit', $governmentId->id) }}">
                        Edit
                    </a>

                    <form
                        method="POST"
                        action="{{ route('admin.government-ids.destroy', $governmentId->id) }}"
                        style="display: inline;"
                    >
                        @csrf
                        @method('DELETE')

                        <button type="submit">Delete</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

@endsection