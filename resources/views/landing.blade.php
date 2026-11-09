@php($contact = config('services.idireksyon.contact_email'))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IDireksyon | Your Guide to Government IDs</title>
    <meta name="description" content="IDireksyon is a student-developed mobile app that helps Santa Maria, Bulacan residents understand government ID requirements, plan what to prepare first, and find the right office.">
    <link rel="canonical" href="https://www.idireksyon.online/">
    <link rel="icon" href="/images/idireksyon-logo.svg" type="image/svg+xml">
    <meta name="theme-color" content="#103765">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="IDireksyon">
    <meta property="og:title" content="IDireksyon | Your Guide to Government IDs">
    <meta property="og:description" content="Know which ID to get, what to prepare first, and where to go. An independent student project for Santa Maria, Bulacan residents.">
    <meta property="og:url" content="https://www.idireksyon.online/">
    <meta property="og:image" content="https://www.idireksyon.online/landing/img/og.png">
    <link rel="preload" href="/landing/fonts/GoogleSans.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/landing/landing.css?v=3">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

{{-- Icon set used across the page --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s7-6.1 7-11.5A7 7 0 0 0 5 9.5C5 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></symbol>
    <symbol id="i-doc" viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/></symbol>
    <symbol id="i-route" viewBox="0 0 24 24"><circle cx="6" cy="19" r="2.5"/><circle cx="18" cy="5" r="2.5"/><path d="M8.5 19H15a3.5 3.5 0 0 0 0-7H9a3.5 3.5 0 0 1 0-7h6.5"/></symbol>
    <symbol id="i-order" viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="5" rx="1.5"/><rect x="4" y="16" width="16" height="5" rx="1.5"/><path d="M12 8v7M9 12.5l3 3 3-3"/></symbol>
    <symbol id="i-ready" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/></symbol>
    <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6.5L20.5 7"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l7 3v5.5c0 4.6-3 8-7 9.5-4-1.5-7-4.9-7-9.5V6z"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6.5 7l1 13h9l1-13M10 11v5M14 11v5"/></symbol>
    <symbol id="i-home" viewBox="0 0 24 24"><path d="M4 11l8-7 8 7v9h-5v-6H9v6H4z"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 21c1-4.5 4-6.5 7.5-6.5s6.5 2 7.5 6.5"/></symbol>
    <symbol id="i-folder" viewBox="0 0 24 24"><path d="M3 6.5h6.5l2 2.5H21V19H3z"/></symbol>
    <symbol id="i-id" viewBox="0 0 24 24"><rect x="3" y="5.5" width="18" height="13" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M5.5 16c.6-1.7 1.6-2.4 3-2.4s2.4.7 3 2.4M14.5 10h4M14.5 14h4"/></symbol>
</svg>

<header class="top">
    <div class="wrap top-row">
        <a class="brand" href="#top" aria-label="IDireksyon home">
            <img src="/images/idireksyon-logo.svg" width="40" height="27" alt="">
            <span>IDireksyon</span>
        </a>
        <nav aria-label="On this page">
            <a href="#about">About</a>
            <a href="#features">Features</a>
            <a href="#contact">Contact</a>
        </nav>
    </div>
</header>

<main id="main">

    {{-- HOME / HERO --}}
    <section class="hero" id="top">
        <svg class="route-line" viewBox="0 0 1200 640" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
            <path d="M-20 560 C 180 560 240 420 420 430 S 640 610 820 520 S 1010 170 1230 120"/>
        </svg>
        <div class="wrap hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><svg class="i"><use href="#i-pin"/></svg> For Santa Maria, Bulacan residents</p>
                <h1>Your Guide to Government IDs.</h1>
                <p class="lede">Know which ID to get, what to prepare first, and which office to visit. Diro walks you through it, one step at a time.</p>
                <div class="actions">
                    <a class="btn btn-solid" href="#features">See what it can do <svg class="i"><use href="#i-arrow"/></svg></a>
                    <a class="btn btn-quiet" href="#about">About the project</a>
                </div>
                <p class="status"><span class="dot" aria-hidden="true"></span> Android app in preparation for Google Play closed testing</p>
            </div>

            <div class="hero-art">
                <div class="phone" role="img" aria-label="Preview of the IDireksyon app home screen, with a Get started banner, quick actions, and recommended documents.">
                    <div class="screen" aria-hidden="true">
                        <p class="ph-title">My ID Journey</p>
                        <div class="ph-banner">
                            <p class="ph-kicker">YOUR NEXT CHAPTER</p>
                            <p class="ph-head">The right IDs. Clear steps. Let’s get you started.</p>
                            <span class="ph-btn">Get started</span>
                        </div>
                        <p class="ph-label">Quick actions</p>
                        <div class="ph-tiles">
                            <span><svg class="i"><use href="#i-folder"/></svg>Document Inventory</span>
                            <span><svg class="i"><use href="#i-pin"/></svg>Office Finder</span>
                            <span><svg class="i"><use href="#i-id"/></svg>ID Directory</span>
                        </div>
                        <p class="ph-label">Recommended for you</p>
                        <div class="ph-row"><svg class="i"><use href="#i-doc"/></svg><span><b>PSA Birth Certificate</b><small>Philippine Statistics Authority</small></span></div>
                        <div class="ph-row"><svg class="i"><use href="#i-id"/></svg><span><b>PhilSys National ID</b><small>Philippine Statistics Authority</small></span></div>
                        <div class="ph-nav">
                            <span class="on"><svg class="i"><use href="#i-home"/></svg>Home</span>
                            <span><svg class="i"><use href="#i-route"/></svg>Roadmap</span>
                            <span><svg class="i"><use href="#i-id"/></svg>IDirectory</span>
                            <span><svg class="i"><use href="#i-user"/></svg>Profile</span>
                        </div>
                    </div>
                </div>
                <div class="diro-cycle" role="img" aria-label="Diro, the IDireksyon mascot, a friendly character with a map pin on its head and an ID lanyard.">
                    <img src="/landing/img/diro-wave.webp" width="640" height="640" alt="" fetchpriority="high">
                    <img src="/landing/img/diro-guide.webp" width="640" height="640" alt="">
                    <img src="/landing/img/diro-prepared.webp" width="640" height="640" alt="">
                    <img src="/landing/img/diro-tada.webp" width="640" height="640" alt="">
                </div>
            </div>
        </div>

        <div class="wrap">
            <dl class="facts">
                <div><dt>30+</dt><dd>government IDs</dd></div>
                <div><dt>40+</dt><dd>supporting documents</dd></div>
                <div><dt>20+</dt><dd>government offices</dd></div>
                <div><dt>24</dt><dd>Santa Maria barangays</dd></div>
            </dl>
        </div>
    </section>

    {{-- NOTICE --}}
    <div class="wrap">
        <p class="notice">
            <svg class="i"><use href="#i-shield"/></svg>
            <span><strong>IDireksyon is an independent student project.</strong> It is not an official government service and is not affiliated with, endorsed by, or operated by any government agency.</span>
        </p>
    </div>

    {{-- ABOUT --}}
    <section class="section" id="about">
        <div class="wrap">
            <div class="about">
                <div class="about-diro">
                    <p class="bubble">Hi! I’m Diro, your ID guide.</p>
                    <img src="/landing/img/diro-guide.webp" width="640" height="640" alt="Diro holding a map and pointing the way." loading="lazy">
                </div>
                <div class="about-copy">
                    <p class="eyebrow eyebrow-light">About IDireksyon</p>
                    <h2>A simpler way to understand your government ID journey.</h2>
                    <p>Getting your first government ID can be confusing. Many IDs ask for another ID or document first, and it is hard to know where to begin.</p>
                    <p>IDireksyon is a student-developed guidance app, built by GeoBytes as an academic capstone project. It is made especially for residents and first-time applicants in Santa Maria, Bulacan.</p>
                </div>
            </div>

            <div class="scope">
                <div class="scope-card">
                    <h3>What IDireksyon helps you do</h3>
                    <ul class="ticks">
                        <li><svg class="i"><use href="#i-check"/></svg>Understand the requirements for each ID</li>
                        <li><svg class="i"><use href="#i-check"/></svg>Keep track of the IDs and documents you have</li>
                        <li><svg class="i"><use href="#i-check"/></svg>Plan which ones to prepare first</li>
                        <li><svg class="i"><use href="#i-check"/></svg>Find the relevant government offices</li>
                    </ul>
                </div>
                <div class="scope-card scope-card-plain">
                    <h3>What the app does not do</h3>
                    <ul class="ticks ticks-no">
                        <li><svg class="i"><use href="#i-x"/></svg>Issue government IDs or documents</li>
                        <li><svg class="i"><use href="#i-x"/></svg>Submit or process applications</li>
                        <li><svg class="i"><use href="#i-x"/></svg>Accept payments</li>
                        <li><svg class="i"><use href="#i-x"/></svg>Book appointments</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- FEATURES --}}
    <section class="section section-white" id="features">
        <div class="wrap">
            <div class="section-head section-head-center">
                <p class="eyebrow eyebrow-light">Features</p>
                <h2>Everything you need, step by step</h2>
                <p>From knowing what to bring to finding where to go, IDireksyon guides you through the whole process.</p>
            </div>

            <div class="features">
                <article class="card">
                    <span class="badge"><svg class="i"><use href="#i-doc"/></svg></span>
                    <h3>ID Requirements</h3>
                    <p>See the documents needed for each government ID in one clear checklist.</p>
                </article>

                <article class="card">
                    <span class="badge"><svg class="i"><use href="#i-order"/></svg></span>
                    <h3>Smart Sequencing</h3>
                    <p>Learn which one to get first, since some IDs and documents are needed for others.</p>
                </article>

                <article class="card">
                    <span class="badge"><svg class="i"><use href="#i-route"/></svg></span>
                    <h3>Personalized Roadmap</h3>
                    <p>Get a step-by-step path based on the IDs and documents you already have.</p>
                </article>

                <article class="card card-wide">
                    <span class="badge"><svg class="i"><use href="#i-ready"/></svg></span>
                    <h3>Readiness Checker</h3>
                    <p>See how close you are to applying before you make the trip to the office.</p>
                </article>

                <article class="card card-wide">
                    <span class="badge"><svg class="i"><use href="#i-pin"/></svg></span>
                    <h3>Office Finder</h3>
                    <p>Find the government offices in and near Santa Maria, Bulacan, and get directions.</p>
                </article>
            </div>

            <p class="confirm">Requirements, fees, and office schedules can change. Always confirm the latest details with the agency before you apply, pay, or travel.</p>
        </div>
    </section>

    {{-- CONTACT + LEGAL --}}
    <section class="section" id="contact">
        <div class="wrap">
            <div class="contact">
                <div class="contact-main">
                    <p class="eyebrow eyebrow-dark">Contact</p>
                    <h2>Questions or feedback?</h2>
                    <p>We would love to hear from you, especially if you spot something that needs correcting.</p>
                    @if($contact)
                        <a class="btn btn-white" href="mailto:{{ $contact }}"><svg class="i"><use href="#i-mail"/></svg>{{ $contact }}</a>
                    @else
                        <p><strong>A support email has not been published yet.</strong></p>
                    @endif
                    <img src="/landing/img/diro-wave.webp" width="640" height="640" alt="Diro waving." loading="lazy">
                </div>
                <div class="legal">
                    <a href="{{ route('privacy', [], false) }}"><svg class="i"><use href="#i-shield"/></svg><span><b>Privacy Policy</b><small>What the app collects and how it is used</small></span><svg class="i go"><use href="#i-arrow"/></svg></a>
                    <a href="{{ route('terms', [], false) }}"><svg class="i"><use href="#i-doc"/></svg><span><b>Terms of Service</b><small>What the app does and does not do</small></span><svg class="i go"><use href="#i-arrow"/></svg></a>
                    <a href="{{ route('account-deletion', [], false) }}"><svg class="i"><use href="#i-trash"/></svg><span><b>Account Deletion</b><small>How to delete your account and data</small></span><svg class="i go"><use href="#i-arrow"/></svg></a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="foot">
    <div class="wrap foot-row">
        <div>
            <p class="foot-brand"><img src="/images/idireksyon-logo.svg" width="32" height="22" alt=""> IDireksyon by GeoBytes</p>
            <p>An independent student project. Not an official government service.</p>
        </div>
        <p class="foot-copy">© {{ date('Y') }} GeoBytes</p>
    </div>
</footer>
</body>
</html>
