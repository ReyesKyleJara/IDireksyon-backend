@extends('admin.offices.embedded-layout')
@section('content')
<p role="status" class="text-sm text-slate-700">Office saved. Returning to linked offices…</p>
<script>
    window.parent.postMessage({
        type: 'idireksyon:office-created',
        office: @js($createdOffice),
    }, window.location.origin);
</script>
@endsection
