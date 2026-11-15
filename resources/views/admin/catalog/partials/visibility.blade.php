{{-- Hide Entry / Show Entry. Only changes whether residents can see the record; nothing is deleted. --}}
@php
    $visibilityRoute = ($recordKind === 'document' ? 'admin.documents' : 'admin.government-ids').'.visibility';
    $hidden = $governmentId->isHidden();
    $kindWord = $recordKind === 'document' ? 'document' : 'ID';
@endphp
<form method="POST" action="{{ route($visibilityRoute, $governmentId) }}" data-visibility-form
    onsubmit="return confirm(@js($hidden
        ? 'Show this '.$kindWord.' to residents again? It will appear in the app as it is now.'
        : 'Hide this '.$kindWord.' from residents? It will disappear from the app until you show it again. Nothing is deleted, and you can still edit it here.'))">
    @csrf
    <input type="hidden" name="hidden" value="{{ $hidden ? 0 : 1 }}">
    <button type="submit" class="admin-secondary">{{ $hidden ? 'Show entry' : 'Hide entry' }}</button>
</form>
