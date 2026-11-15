@php
    $dataSources = app(\App\Services\CatalogAgencyValidation::class)->progress($governmentId);
    $onlineCount = count($governmentId->research_metadata['sources'] ?? []);
    $agencyCount = $governmentId->agencyVerifications()->count();
@endphp
<section class="admin-panel mb-5 flex flex-wrap items-center justify-between gap-3 p-4 text-sm" aria-label="Data Sources summary" data-progress="{{ $dataSources['key'] }}">
    <div>
        <p class="text-xs font-semibold uppercase text-slate-500">Data Sources</p>
        <p class="text-base font-semibold">{{ $dataSources['label'] }}</p>
        <p class="text-slate-600">{{ $dataSources['reasons'][0] ?? '' }}</p>
        <p class="text-slate-500">{{ $onlineCount }} online {{ $onlineCount === 1 ? 'source' : 'sources' }} · {{ $agencyCount }} agency {{ $agencyCount === 1 ? 'record' : 'records' }}</p>
    </div>
    <p class="text-slate-500">Managed in Edit details → Basic Information → Data Sources.</p>
</section>
