@php
    $dates = \App\Services\CatalogReviewDates::state($record);
    $updated = $dates['last_updated'] ?? $record->updated_at;
    $reviewed = $dates['last_reviewed'];
@endphp
<div class="space-y-1 text-xs text-slate-600" data-directory-dates>
    @if($updated)
        <p>Last updated: <time datetime="{{ $updated->toIso8601String() }}">{{ $updated->copy()->timezone('Asia/Manila')->format('j M Y') }}</time></p>
    @endif
    @if($reviewed)
        <p>Last reviewed: <time datetime="{{ $reviewed->toIso8601String() }}">{{ $reviewed->copy()->timezone('Asia/Manila')->format('j M Y') }}</time></p>
    @endif
</div>
