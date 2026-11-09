@extends('legal.layout')
@section('title', 'Terms of Service')
@section('content')
@php($contact = config('services.idireksyon.contact_email'))
<h1>Terms of Service</h1>
<p class="updated">Last updated: November 9, 2026</p>

<p class="notice"><strong>A guide, not a government service.</strong> Official government agencies remain the authority on ID requirements and applications.</p>

<p style="margin-top: 20px">These terms explain what IDireksyon does, what it does not do, and what we expect when you use it. By creating an account, you agree to them.</p>

<h2>What IDireksyon is</h2>
<p>IDireksyon is an independent academic project developed by GeoBytes. It helps you understand Philippine government ID requirements, keep track of the IDs and documents you have, plan which ones to prepare, and find relevant offices.</p>

<h2>Informational guidance only</h2>
<p>Everything in the app is for information and preparation. Roadmaps and suggestions are based on what you enter and are meant to help you get ready. They are not official advice or a decision about your application.</p>

<h2>Not an official government service</h2>
<p>IDireksyon is not affiliated with, endorsed by, or operated by any government agency. The app does not:</p>
<ul>
    <li>issue government IDs or documents</li>
    <li>submit or process applications</li>
    <li>accept payments</li>
    <li>book appointments</li>
</ul>

<h2>Information can change</h2>
<p>Requirements, fees, office locations and schedules, and procedures are set by government agencies and can change at any time. Information in the app may not always reflect the latest updates.</p>

<h2>Check with official sources</h2>
<p>Before you apply, pay, or travel to an office, confirm the important details with the relevant agency through its official website, hotline, or office.</p>

<h2>Your responsibilities</h2>
<ul>
    <li>Provide accurate information about yourself.</li>
    <li>Keep your password private, and use your account only for yourself.</li>
    <li>Review your own documents and complete official applications with the relevant agency.</li>
</ul>

<h2>Acceptable use</h2>
<p>When using IDireksyon, please do not:</p>
<ul>
    <li>use the app for anything unlawful or fraudulent</li>
    <li>access or try to access another person's account</li>
    <li>interfere with the app or its servers, or try to get around its security</li>
    <li>present IDireksyon or its content as an official government service</li>
</ul>

<h2>Deleting or ending an account</h2>
<p>You can permanently delete your account at any time in Profile &gt; Password &amp; security. Deletion happens right away and cannot be undone. If you cannot log in, see <a href="{{ route('account-deletion', [], false) }}">Account Deletion</a>.</p>

<h2>What we cannot guarantee</h2>
<p>Using IDireksyon does not guarantee eligibility, approval, or the issuance of any ID or document. Those decisions belong to the government agency concerned.</p>
<p>The app is provided as it is. To the extent allowed by law, GeoBytes is not responsible for losses that result from relying on information in the app without confirming it with official sources.</p>

<h2>External services</h2>
<p>Some features open map services or government websites. These are run independently and have their own terms and privacy practices.</p>

<h2>Availability</h2>
<p>Some features need an internet connection. As an academic project, IDireksyon may go through testing, maintenance, or changes, and some functions may not always be available.</p>

<h2>Changes to these terms</h2>
<p>These terms may be updated as the app changes. The date at the top shows the latest version.</p>

<h2>Contact</h2>
@if($contact)
    <p>Questions about these terms can be sent to GeoBytes at <a href="mailto:{{ $contact }}">{{ $contact }}</a>.</p>
@else
    <p>A contact email has not been published yet.</p>
@endif
@endsection
