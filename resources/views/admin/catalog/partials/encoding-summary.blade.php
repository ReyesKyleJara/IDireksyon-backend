@php($metadata = $governmentId->research_metadata ?? [])
<section id="record-sources" class="admin-panel space-y-4 p-6" aria-label="Saved research and request channels">
    <h2 class="font-semibold">Sources &amp; request methods</h2>
    @if(filled($metadata['validity_note'] ?? null))<p>{{ $metadata['validity_note'] }}</p>@endif
    @if(!empty($metadata['hidden_sections']))<p>Hidden resident sections (content retained): {{ implode(', ', $metadata['hidden_sections']) }}</p>@endif
    @if(!empty($metadata['sources']) && is_array($metadata['sources']))
        <ul class="space-y-2 text-sm" aria-label="Official sources">
            @foreach($metadata['sources'] as $source)
                @if(is_array($source) && filled($source['title'] ?? null))
                    <li class="break-words">
                        @if(\App\Support\GuideRichText::safeUrl($source['url'] ?? null))
                            <a class="font-medium text-blue-800 underline" href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['title'] }}</a>
                        @else
                            <span class="font-medium">{{ $source['title'] }}</span>
                        @endif
                        @if(filled($source['checked_on'] ?? null))<span class="text-slate-600"> · Checked {{ $source['checked_on'] }}</span>@endif
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
    @foreach($governmentId->requirementSets as $set)
        @if($set->request_channel)
            @php($channel = $set->request_channel)
            <article class="space-y-2 rounded border p-4">
                <h3 class="font-semibold">{{ $channel['label'] ?? $channel['provider'] ?? $set->display_label }}</h3>
                <p>{{ $set->display_label }} · {{ ucfirst($channel['channel'] ?? 'Not specified') }} · {{ $channel['provider'] ?? '' }}</p>
                <p>Appointment: {{ str_replace('_', ' ', $channel['appointment'] ?? 'not specified') }} · Fulfillment: {{ implode(', ', $channel['fulfillment'] ?? []) }}</p>
                @if(filled($channel['processing'] ?? null))<p>Processing / fulfillment: {{ $channel['processing'] }}</p>@endif
                @if(filled($channel['applicability'] ?? null))<p class="whitespace-pre-line">{{ $channel['applicability'] }}</p>@endif
                @if(\App\Support\GuideRichText::safeUrl($channel['portal_url'] ?? null))<a class="text-blue-800 underline" href="{{ $channel['portal_url'] }}" target="_blank" rel="noopener noreferrer">Official request portal ↗</a>@endif
                <p>Fees: {{ !isset($channel['fee_ids']) ? 'All saved fees' : (empty($channel['fee_ids']) ? 'None selected' : $governmentId->fees->whereIn('id', $channel['fee_ids'])->pluck('label')->implode(', ')) }}</p>
                <p>Offices: {{ !isset($channel['office_ids']) ? 'All saved offices' : (empty($channel['office_ids']) ? 'None — no physical office selected' : $governmentId->offices->whereIn('id', $channel['office_ids'])->pluck('name')->implode(', ')) }}</p>
                @foreach($channel['troubleshooting'] ?? [] as $case)<div><p class="font-medium">Special case: {{ $case['title'] }}</p><p class="whitespace-pre-line">{{ $case['body'] }}</p></div>@endforeach
            </article>
        @endif
    @endforeach
    @foreach(\App\Services\CatalogSpecialCases::all($governmentId) as $case)
        <article class="space-y-2 rounded border p-4">
            <h3 class="font-semibold">Special case guide: {{ $case['title'] }}</h3>
            <p>{{ $case['description'] }}</p>
            <p class="text-sm text-slate-500">{{ count($case['requirements'] ?? []) }} requirement groups · {{ count($case['steps'] ?? []) }} steps · {{ ucfirst(str_replace('_', ' ', $case['status'] ?? 'draft')) }} · Separate from normal request channels</p>
            <a class="text-sm text-blue-800 underline" href="{{ route(($governmentId instanceof \App\Models\Document ? 'admin.documents' : 'admin.government-ids').'.special-cases.edit', [$governmentId, $case['id']]) }}">Review special case guide</a>
        </article>
    @endforeach
</section>
