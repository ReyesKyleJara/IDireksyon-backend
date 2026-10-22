<section class="admin-panel my-5 p-6">
    <h2 class="text-lg font-semibold">Smart Sequencing Prerequisites</h2>
    @if(($recordKind ?? 'government_id') === 'government_id')
        <p class="my-3 text-sm text-slate-600">Explicitly configure what must be obtained before this ID. Requirement references and alternatives do not automatically become prerequisites.</p>
        <a class="admin-secondary" href="{{ route('admin.government-ids.prerequisites.index', $governmentId) }}">Manage prerequisites ({{ $governmentId->prerequisites()->count() }})</a>
        @php($usage = \App\Models\GovernmentIdPrerequisite::with('governmentId')->where('prerequisite_government_id_id', $governmentId->id)->get())
    @else
        @php($usage = \App\Models\GovernmentIdPrerequisite::with('governmentId')->where('prerequisite_document_id', $governmentId->id)->get())
    @endif
    <p class="mt-3 text-sm text-slate-600">Used as a prerequisite by {{ $usage->count() }} entries. Linked records cannot be deleted until these links are removed.</p>
    @foreach($usage as $link)
        <a class="mt-2 block text-sm underline" href="{{ route('admin.government-ids.prerequisites.index', $link->governmentId) }}">{{ $link->governmentId->name }}</a>
    @endforeach
</section>
