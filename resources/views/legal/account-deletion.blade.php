@extends('legal.layout')
@section('title', 'Account Deletion')
@section('content')
@php($contact = config('services.idireksyon.contact_email'))
<h1>Delete your IDireksyon account</h1>
<p class="updated">Last updated: November 9, 2026</p>
<p>This page explains how to delete an account in the IDireksyon mobile app, developed by GeoBytes, and what happens to your information.</p>

<h2>Delete your account in the app</h2>
<ol>
    <li>Open IDireksyon and log in.</li>
    <li>Go to <strong>Profile</strong>, then <strong>Password &amp; security</strong>.</li>
    <li>Tap <strong>Delete account</strong>.</li>
    <li>Enter your password to confirm.</li>
</ol>
<p>Deletion happens right away and cannot be undone.</p>

<h2>What is deleted</h2>
<ul>
    <li>Your account: name, email address or mobile number, password, birth date, and barangay</li>
    <li>The IDs and documents saved in your inventory</li>
    <li>Your Roadmap progress, choices, and completed steps</li>
    <li>The requirements you marked as prepared</li>
    <li>Your sign-in sessions, plus any pending password reset or email verification records</li>
    <li>The profile photo saved on the device you used to delete your account</li>
</ul>
<p>A profile photo stays only on the device where you added it. If you used IDireksyon on another device, remove the photo there or uninstall the app.</p>

<h2>What may remain</h2>
<p>Technical records and backups held by our hosting provider follow that provider's own practices. General government ID and office information in IDireksyon is not personal data and is not affected.</p>

<h2>If you cannot log in</h2>
<p>If your account uses an email address and you only forgot your password, you can use <strong>Forgot password</strong> on the login screen, then delete your account in the app.</p>
@if($contact)
    <p>Otherwise, email <a href="mailto:{{ $contact }}?subject={{ rawurlencode('IDireksyon account deletion request') }}">{{ $contact }}</a> with the subject "IDireksyon account deletion request" and include the email address or mobile number on your account. We may ask you to confirm that the account is yours before deleting it.</p>
@else
    <p>A contact email for deletion requests has not been published yet.</p>
@endif

<p class="notice">IDireksyon is an independent academic project developed by GeoBytes and is not an official government app. See our <a href="{{ route('privacy', [], false) }}">Privacy Policy</a>.</p>
@endsection
