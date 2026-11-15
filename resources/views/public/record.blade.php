{{--
    Public summary of one ID or document, opened from the app's Share button. Read-only.
    It is drawn like the app's own details screen (hero, rounded sheet, pill tabs, cards) and
    follows the visitor's light or dark setting. Only the Overview is shown here; the other
    tabs point to the app.
--}}
@php
    $day = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->timezone('Asia/Manila')->format('j M Y') : null;
    $sourceTitle = fn ($title) => trim(preg_replace('/^\s*public\s+reference\s*[:\-–—]\s*/i', '', (string) $title)) ?: trim((string) $title);
    $summary = 'Requirements, fees and steps for '.$record['name'].'. An independent guide by IDireksyon, not a government website.';
    $dates = $record['review_dates'] ?? [];
    $hasTime = filled($record['processing_time'] ?? null) || filled($record['processing_time_note'] ?? null);
    $hasValidity = filled($record['validity'] ?? null);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $record['name'] }} | IDireksyon</title>
    <meta name="description" content="{{ $summary }}">
    <meta name="color-scheme" content="light dark">
    <link rel="canonical" href="{{ $shareUrl }}">
    <link rel="icon" href="/images/idireksyon-logo.svg" type="image/svg+xml">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="IDireksyon">
    <meta property="og:title" content="{{ $record['name'] }} | IDireksyon">
    <meta property="og:description" content="{{ $summary }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    @if($imageUrl)<meta property="og:image" content="{{ $imageUrl }}">@endif
    <style>
        @font-face { font-family: "Google Sans"; src: url("/landing/fonts/GoogleSans.woff2") format("woff2"); font-weight: 400 700; font-display: swap; }
        :root {
            --page: #F3F8FF; --sheet: #FFFFFF; --card: #FFFFFF; --line: rgba(194, 199, 207, .45);
            --text: #191C20; --muted: #42474E; --accent: #174B85; --pill: #103765; --pill-text: #FFFFFF;
            --tile: #E9EEF5; --notice: #E7EEF8;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --page: #111B28; --sheet: #111B28; --card: #1C2B3D; --line: rgba(140, 150, 165, .28);
                --text: #E3E8EF; --muted: #B4BDC9; --accent: #A8CCFA; --pill: #A8CCFA; --pill-text: #103765;
                --tile: #24344A; --notice: #172536;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--page); color: var(--text); font: 14px/1.5 "Google Sans", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        a { color: var(--accent); }
        .screen { max-width: 520px; margin: 0 auto; min-height: 100vh; background: var(--page); }
        .hero { color: #fff; padding: 14px 24px 44px;
            background: radial-gradient(circle at 105% -10%, rgba(79, 141, 247, .42), transparent 62%),
                radial-gradient(circle at -10% 110%, rgba(7, 31, 69, .55), transparent 58%),
                linear-gradient(135deg, #0B4295 0%, #174B85 55%, #103765 100%); }
        .brand { display: inline-flex; align-items: center; gap: 8px; color: #fff; text-decoration: none; font-weight: 700; font-size: 14px; opacity: .95; }
        .brand img { width: 22px; height: 22px; border-radius: 5px; background: #fff; }
        .hero-top { display: flex; gap: 20px; align-items: center; justify-content: space-between; margin-top: 18px; }
        .hero h1 { margin: 0; font-size: 24px; line-height: 1.25; font-weight: 800; overflow-wrap: anywhere; }
        .quiet { margin: 14px 0 3px; font-size: 12px; line-height: 1.3; color: rgba(255, 255, 255, .75); }
        .issuer { margin: 0; font-size: 15px; line-height: 1.3; font-weight: 600; }
        .hero-image { width: 104px; height: 84px; flex: none; display: flex; align-items: center; justify-content: center; }
        .hero-image img { width: 104px; height: 104px; object-fit: contain; border-radius: 12px; }
        .about { margin: 0; font-size: 13px; line-height: 1.5; color: rgba(255, 255, 255, .92); }
        .sheet { margin-top: -24px; background: var(--sheet); border-radius: 24px 24px 0 0; padding-top: 18px; }
        .tabs { display: flex; gap: 2px; padding: 0 12px 10px; overflow-x: auto; scrollbar-width: none; }
        .tabs::-webkit-scrollbar { display: none; }
        .tab { flex: none; height: 34px; padding: 0 14px; display: inline-flex; align-items: center; border-radius: 999px; font-size: 13px; font-weight: 500; color: var(--text); text-decoration: none; }
        .tab.on { background: var(--pill); color: var(--pill-text); font-weight: 700; }
        .content { background: var(--page); padding: 16px 16px 28px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 16px; margin-bottom: 12px; }
        .card h2 { display: flex; align-items: center; gap: 10px; margin: 0 0 12px; font-size: 16px; line-height: 1.35; font-weight: 700; color: var(--accent); }
        .card h2 svg { width: 22px; height: 22px; flex: none; }
        .card p { margin: 0 0 8px; }
        .card p:last-child { margin-bottom: 0; }
        .fact { display: flex; gap: 12px; align-items: flex-start; }
        .fact + .fact { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); }
        .tile { width: 36px; height: 36px; flex: none; border-radius: 10px; background: var(--tile); color: var(--accent); display: flex; align-items: center; justify-content: center; }
        .tile svg { width: 20px; height: 20px; }
        .fact small { display: block; font-size: 13px; font-weight: 600; color: var(--muted); }
        .fact b { display: block; font-size: 16px; line-height: 1.35; font-weight: 700; }
        .fact span { display: block; font-size: 13px; color: var(--muted); }
        ul.dots { margin: 0; padding: 0; list-style: none; }
        ul.dots li { position: relative; padding-left: 15px; margin-top: 8px; }
        ul.dots li:first-child { margin-top: 0; }
        ul.dots li::before { content: ""; position: absolute; left: 0; top: 9px; width: 5px; height: 5px; border-radius: 50%; background: var(--accent); }
        .fee { display: flex; justify-content: space-between; gap: 12px; }
        .fee + .fee { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--line); }
        .fee b { font-weight: 700; white-space: nowrap; }
        .muted { color: var(--muted); font-size: 13px; }
        .notice { background: var(--notice); border-color: transparent; font-size: 13px; color: var(--muted); }
        .notice h2 { font-size: 14px; margin-bottom: 6px; }
        .source-head { display: flex; gap: 10px; align-items: flex-start; }
        .source-head svg { width: 20px; height: 20px; flex: none; margin-top: 1px; color: var(--accent); }
        .source-head b { display: block; font-size: 15px; line-height: 1.35; }
        .source hr { border: 0; border-top: 1px solid var(--line); margin: 11px 0; }
        .label { margin: 0 0 4px; font-size: 12px; letter-spacing: .2px; font-weight: 700; color: var(--accent); }
        .source-title { margin: 0; font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
        .link { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 8px 4px; font-weight: 600; text-decoration: none; }
        .link svg { width: 16px; height: 16px; }
        .dates { display: flex; gap: 8px; padding: 4px 4px 12px; color: var(--muted); font-size: 13px; }
        .dates svg { width: 18px; height: 18px; flex: none; margin-top: 1px; }
        footer { padding: 4px 8px 12px; text-align: center; color: var(--muted); font-size: 13px; }
    </style>
</head>
<body>
<main class="screen">
    <header class="hero">
        <a class="brand" href="{{ route('home', [], false) }}"><img src="/images/idireksyon-logo.svg" alt="">IDireksyon</a>
        <div class="hero-top">
            <div>
                <h1>{{ $record['name'] }}</h1>
                @if(filled($record['issued_by'] ?? null))
                    <p class="quiet">Issued by</p>
                    <p class="issuer">{{ $record['issued_by'] }}</p>
                @endif
            </div>
            @if($imageUrl)<div class="hero-image"><img src="{{ $imageUrl }}" alt=""></div>@endif
        </div>
        @if(filled($record['description'] ?? null))
            <p class="quiet">About</p>
            <p class="about">{{ $record['description'] }}</p>
        @endif
    </header>

    <div class="sheet">
        <nav class="tabs" aria-label="Sections">
            <span class="tab on" aria-current="page">Overview</span>
            @foreach(['Requirements', 'Cost', 'Offices', 'Guide'] as $tab)
                <a class="tab" href="#in-app">{{ $tab }}</a>
            @endforeach
        </nav>

        <div class="content">
            @if($hasTime || $hasValidity)
                <section class="card" aria-label="Key information">
                    @if($hasTime)
                        <div class="fact">
                            <div class="tile"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg></div>
                            <div>
                                <small>Processing Time</small>
                                @if(filled($record['processing_time'] ?? null))<b>{{ $record['processing_time'] }}</b>@endif
                                @if(filled($record['processing_time_note'] ?? null))<span>{{ $record['processing_time_note'] }}</span>@endif
                            </div>
                        </div>
                    @endif
                    @if($hasValidity)
                        <div class="fact">
                            <div class="tile"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.53 11.06 15.47 10l-4.88 4.88-2.12-2.12-1.06 1.06L10.59 17l5.94-5.94zM19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg></div>
                            <div>
                                <small>Validity</small>
                                <b>{{ $record['validity'] }}</b>
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            @if($eligibility)
                <section class="card">
                    <h2><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>Eligibility</h2>
                    @if(count($eligibility) > 1)
                        <ul class="dots">@foreach($eligibility as $line)<li>{{ $line }}</li>@endforeach</ul>
                    @else
                        <p>{{ $eligibility[0] }}</p>
                    @endif
                </section>
            @endif

            @if($fees)
                <section class="card">
                    <h2><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>Cost</h2>
                    @foreach($fees as $fee)
                        <div class="fee">
                            <div>
                                {{ $fee['label'] ?? 'Fee' }}
                                @if(! empty($fee['options']))
                                    <div class="muted">{{ collect($fee['options'])->map(fn ($option) => trim(($option['label'] ?? '').' '.($option['display_amount'] ?? '')))->filter()->implode(' · ') }}</div>
                                @endif
                            </div>
                            @if(filled($fee['display_amount'] ?? null))<b>{{ $fee['display_amount'] }}</b>@endif
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="card" id="in-app">
                <h2><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17 1.01 7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>See the full guide in the IDireksyon app</h2>
                <p>The app has the complete requirements checklist, @if($waysToApply > 1){{ $waysToApply }} ways to apply, @endif the step-by-step guide and nearby offices for {{ $record['name'] }}.</p>
                <p><a href="{{ route('home', [], false) }}">About IDireksyon</a></p>
            </section>

            @foreach($sources as $source)
                <section class="card source">
                    <div class="source-head">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M12 6.5C10.3 5.2 7.9 4.6 4 4.6v13.2c3.9 0 6.3.6 8 1.9m0-13.2c1.7-1.3 4.1-1.9 8-1.9v13.2c-3.9 0-6.3.6-8 1.9m0-13.2v13.2"/></svg>
                        <div>
                            <b>{{ $record['name'] }}</b>
                            @if(filled($record['issued_by'] ?? null))<span class="muted">{{ $record['issued_by'] }}</span>@endif
                        </div>
                    </div>
                    <hr>
                    <p class="label">Public reference</p>
                    <p class="source-title">{{ $sourceTitle($source['title'] ?? '') }}</p>
                    <a class="link" href="{{ $source['url'] }}" rel="noopener noreferrer nofollow" target="_blank">View source webpage
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg></a>
                    @if(filled($source['scope'] ?? null))<p class="muted">{{ $source['scope'] }}</p>@endif
                    @if($day($source['checked_on'] ?? null))<p class="muted">Checked on {{ $day($source['checked_on']) }}</p>@endif
                </section>
            @endforeach

            @if($day($dates['last_updated_at'] ?? null) || $day($dates['last_reviewed_at'] ?? null))
                <div class="dates">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21 10.12h-6.78l2.74-2.82c-2.73-2.7-7.15-2.8-9.88-.1-2.73 2.71-2.73 7.08 0 9.79s7.15 2.71 9.88 0C18.32 15.65 19 14.08 19 12.1h2c0 1.98-.88 4.55-2.64 6.29-3.51 3.48-9.21 3.48-12.72 0-3.5-3.47-3.53-9.11-.02-12.58s9.14-3.47 12.65 0L21 3v7.12zM12.5 8v4.25l3.5 2.08-.72 1.21L11 13V8h1.5z"/></svg>
                    <div>
                        @if($day($dates['last_updated_at'] ?? null))<div>Last updated: {{ $day($dates['last_updated_at']) }}</div>@endif
                        @if($day($dates['last_reviewed_at'] ?? null))<div>Last reviewed by the IDireksyon team: {{ $day($dates['last_reviewed_at']) }}</div>@endif
                    </div>
                </div>
            @endif

            <section class="card notice">
                <h2>{{ $kind }} information from public sources</h2>
                <p>IDireksyon is an independent academic project. It is not a government website and is not affiliated with,
                    endorsed by, or operated by any government agency. Always confirm with the issuing office before applying.
                    @if($sources)Listing a public reference does not mean the agency reviewed or endorsed IDireksyon.@endif</p>
            </section>

            <footer>
                <a href="{{ route('home', [], false) }}">Home</a> ·
                <a href="{{ route('privacy', [], false) }}">Privacy Policy</a> ·
                <a href="{{ route('terms', [], false) }}">Terms of Service</a>
            </footer>
        </div>
    </div>
</main>
</body>
</html>
