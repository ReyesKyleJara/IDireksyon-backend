{{--
    Last Updated (automatic: information changed) and Last Reviewed ("Mark as reviewed": a team
    member reviewed the whole record). Separate from online sources and from Agency Confirmation.
--}}
@php
    $reviewDates = \App\Services\CatalogReviewDates::state($governmentId);
    $reviewDay = fn ($value) => $value?->copy()->timezone('Asia/Manila')->format('j M Y');
    $reviewTime = fn ($value) => $value?->copy()->timezone('Asia/Manila')->format('F j, Y · g:i A').' PHT';
    $reviewRoute = ($recordKind === 'document' ? 'admin.documents' : 'admin.government-ids').'.review';
@endphp
<div class="mt-4 flex flex-wrap items-center text-sm" style="column-gap: 24px; row-gap: 8px;" data-review-dates>
    <p data-last-updated @if($reviewDates['last_updated']) title="{{ $reviewTime($reviewDates['last_updated']) }}" @endif>
        <span class="text-slate-500">Last updated:</span>
        <span class="font-medium text-slate-800">{{ $reviewDay($reviewDates['last_updated']) ?? 'Not recorded' }}</span>
    </p>
    <p data-last-reviewed @if($reviewDates['last_reviewed']) title="{{ $reviewTime($reviewDates['last_reviewed']) }}" @endif>
        <span class="text-slate-500">Last reviewed:</span>
        @if($reviewDates['last_reviewed'])
            <span class="font-medium text-slate-800">{{ $reviewDay($reviewDates['last_reviewed']) }}</span>
            <span class="text-slate-500">by {{ $governmentId->lastReviewer?->name ?? 'a former team member' }}</span>
        @else
            <span class="font-medium text-slate-800">Not reviewed yet</span>
        @endif
    </p>
    <form method="POST" action="{{ route($reviewRoute, $governmentId) }}"
        onsubmit="return confirm(@js('Mark this whole '.($recordKind === 'document' ? 'document' : 'ID').' as reviewed today? This saves today’s date and your name as the last review. It is not an agency confirmation.'))">
        @csrf
        <input type="hidden" name="_catalog_revision" value="{{ \App\Services\CatalogRevision::token($governmentId) }}">
        <button type="submit" class="admin-secondary">Mark as reviewed</button>
    </form>
</div>
@if($reviewDates['updated_since_review'])
    <p class="mt-2 text-sm text-amber-800" data-updated-since-review>Updated after the last review. It may need reviewing again.</p>
@endif
