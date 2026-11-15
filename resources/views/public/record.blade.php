{{-- Public summary of one ID or document, opened from the app's Share button. Read-only. --}}
@extends('legal.layout')

@php
    $day = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->timezone('Asia/Manila')->format('j M Y') : null;
    $sourceTitle = fn ($title) => trim(preg_replace('/^\s*public\s+reference\s*[:\-–—]\s*/i', '', (string) $title)) ?: trim((string) $title);
    $summary = 'Requirements, fees and steps for '.$record['name'].'. An independent guide by IDireksyon, not a government website.';
    $dates = $record['review_dates'] ?? [];
@endphp

@section('title', $record['name'])

@section('head')
    <meta name="description" content="{{ $summary }}">
    <link rel="canonical" href="{{ $shareUrl }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="IDireksyon">
    <meta property="og:title" content="{{ $record['name'] }} | IDireksyon">
    <meta property="og:description" content="{{ $summary }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    @if($imageUrl)<meta property="og:image" content="{{ $imageUrl }}">@endif
    <style>
        .record-top { display: flex; gap: 16px; align-items: flex-start; justify-content: space-between; }
        .record-top img { width: 96px; height: 96px; object-fit: contain; border-radius: 12px; flex: none; }
        .kind { margin: 0 0 6px; color: #5b6b7f; font-size: 14px; }
        .issued { margin: 6px 0 0; color: #263c5c; }
        .facts { margin: 0; }
        .facts dt { margin-top: 14px; color: #5b6b7f; font-size: 14px; }
        .facts dd { margin: 2px 0 0; font-weight: 600; }
        .facts dd small, .muted { color: #5b6b7f; font-size: 14px; font-weight: 400; }
        .source { margin: 0 0 12px; padding: 12px 14px; border: 1px solid #e3ecf7; border-radius: 12px; }
        .source p { margin: 0; }
        .about { color: #5b6b7f; font-size: 14px; }
    </style>
@endsection

@section('content')
    <div class="record-top">
        <div>
            <p class="kind">{{ $kind }}@if(filled($record['category'] ?? null)) · {{ $record['category'] }}@endif</p>
            <h1>{{ $record['name'] }}</h1>
            @if(filled($record['issued_by'] ?? null))<p class="issued">Issued by <strong>{{ $record['issued_by'] }}</strong></p>@endif
        </div>
        @if($imageUrl)<img src="{{ $imageUrl }}" alt="">@endif
    </div>

    <p class="notice">
        IDireksyon is an independent academic project. It is not a government website and is not affiliated with,
        endorsed by, or operated by any government agency. Always confirm with the issuing office before applying.
    </p>

    @if(filled($record['processing_time'] ?? null) || filled($record['processing_time_note'] ?? null) || filled($record['validity'] ?? null) || $eligibility || $fees)
        <h2>Quick summary</h2>
        <dl class="facts">
            @if(filled($record['processing_time'] ?? null) || filled($record['processing_time_note'] ?? null))
                <dt>Processing time</dt>
                <dd>{{ $record['processing_time'] ?? '' }} @if(filled($record['processing_time_note'] ?? null))<small>{{ $record['processing_time_note'] }}</small>@endif</dd>
            @endif
            @if(filled($record['validity'] ?? null))
                <dt>Validity</dt>
                <dd>{{ $record['validity'] }}</dd>
            @endif
            @if($eligibility)
                <dt>Who can apply</dt>
                @foreach($eligibility as $line)<dd>{{ $line }}</dd>@endforeach
            @endif
            @if($fees)
                <dt>Fees</dt>
                @foreach($fees as $fee)
                    <dd>{{ $fee['label'] ?? 'Fee' }}@if(filled($fee['display_amount'] ?? null)): {{ $fee['display_amount'] }}@endif
                        @if(! empty($fee['options']))
                            <small>{{ collect($fee['options'])->map(fn ($option) => trim(($option['label'] ?? '').' '.($option['display_amount'] ?? '')))->filter()->implode(' · ') }}</small>
                        @endif
                    </dd>
                @endforeach
            @endif
        </dl>
    @endif

    <h2>See the full guide in the IDireksyon app</h2>
    <p>The app has the complete requirements checklist, @if($waysToApply > 1){{ $waysToApply }} ways to apply, @endif the step-by-step guide and nearby offices for {{ $record['name'] }}.</p>
    <p><a href="{{ route('home', [], false) }}">About IDireksyon</a></p>

    @if($sources)
        <h2>Public references</h2>
        <p class="muted">Publicly available web pages this information is based on. Listing a page does not mean the agency reviewed or endorsed IDireksyon.</p>
        @foreach($sources as $source)
            <div class="source">
                <p><strong>{{ $sourceTitle($source['title'] ?? '') }}</strong></p>
                @if(filled($source['scope'] ?? null))<p class="muted">{{ $source['scope'] }}</p>@endif
                <p><a href="{{ $source['url'] }}" rel="noopener noreferrer nofollow" target="_blank">View source webpage</a>
                    @if($day($source['checked_on'] ?? null))<span class="muted"> · Checked on {{ $day($source['checked_on']) }}</span>@endif</p>
            </div>
        @endforeach
    @endif

    @if(filled($record['description'] ?? null))
        <h2>About</h2>
        <p class="about">{{ $record['description'] }}</p>
    @endif

    @if($day($dates['last_updated_at'] ?? null) || $day($dates['last_reviewed_at'] ?? null))
        <p class="updated">
            @if($day($dates['last_updated_at'] ?? null))Last updated: {{ $day($dates['last_updated_at']) }}@endif
            @if($day($dates['last_reviewed_at'] ?? null)) · Last reviewed by the IDireksyon team: {{ $day($dates['last_reviewed_at']) }}@endif
        </p>
    @endif
@endsection
