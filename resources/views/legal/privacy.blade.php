@extends('legal.layout')
@section('title', 'Privacy Policy')
@section('content')
@php($contact = config('services.idireksyon.contact_email'))
<h1>Privacy Policy</h1>
<p class="updated">Last updated: November 9, 2026</p>

<p class="notice"><strong>IDireksyon is an independent academic project developed by GeoBytes.</strong> It is not an official government app and is not affiliated with, endorsed by, or operated by any government agency.</p>

<h2>About this policy</h2>
<p>IDireksyon helps residents understand Philippine government ID requirements, keep track of their IDs and documents, and plan the steps for getting them. This policy explains what the IDireksyon mobile app collects and how it is used.</p>

<h2>Information you give us</h2>
<ul>
    <li><strong>Account details:</strong> your email address or mobile number, and your password. Passwords are stored in hashed form, not as readable text.</li>
    <li><strong>Profile details:</strong> your name, birth date, and barangay.</li>
    <li><strong>IDs and documents you own:</strong> the items you mark as already having in your inventory.</li>
    <li><strong>Roadmap progress:</strong> your selected target IDs, saved roadmap progress, completed steps, choices you make, and requirements you mark as prepared.</li>
</ul>
<p>The app does not ask you to upload copies of your IDs or documents, and does not ask for their ID numbers.</p>

<h2>How we use your information</h2>
<p>We use this information to run your account, save your inventory and roadmap progress, and show guidance based on what you enter. We also use your email address to send sign-up verification codes and password reset links.</p>

<h2>Location</h2>
<p>Office Finder can use your device's location to show offices near you and to get directions. The app asks for permission first, and only uses location while you are using the app. It may ask when you tap "Use my current location", request directions, or start typing in the Office Finder search. You can decline and still browse offices.</p>
<p>When you request directions inside Office Finder, the app sends your current location and the office's location to the IDireksyon server so a route can be calculated with Google Maps Platform. Your location is not saved to your IDireksyon account.</p>
<p>The "Get Directions" option on ID and document pages opens Google Maps with the office as the destination.</p>

<h2>Profile photo</h2>
<p>If you add a profile photo, it stays on your device and is not uploaded to IDireksyon's servers. You can change or remove it in the app.</p>

<h2>Services we use</h2>
<ul>
    <li><strong>Railway</strong> hosts the IDireksyon server and database, where your account information is stored.</li>
    <li><strong>Google Maps Platform</strong> provides the maps, office locations, and directions. Google handles information it receives under its own privacy policy.</li>
    <li><strong>Resend</strong> delivers verification and password reset emails, and receives your email address for that purpose.</li>
</ul>
<p>Our hosting provider may keep technical request records, such as IP addresses and access times, as part of running the service.</p>

<h2>We do not sell your data</h2>
<p>IDireksyon does not sell your personal information. The app does not contain advertising or analytics SDKs.</p>

<h2>How your information is protected</h2>
<p>The app connects to the production IDireksyon server over HTTPS. Your sign-in session is kept in your device's secure storage and expires after 30 days. No app or service can guarantee complete security, so please keep your password private.</p>

<h2>Keeping and deleting your information</h2>
<p>Your account information stays saved until your account is deleted. You can edit your details, update your inventory, change your password, or permanently delete your account in the app. Technical records and backups held by our hosting provider follow that provider's own practices.</p>
<p>See <a href="{{ route('account-deletion', [], false) }}">Account Deletion</a> for what is removed and how to request deletion if you cannot log in.</p>

<h2>Changes to this policy</h2>
<p>We may update this policy as the app changes. The date at the top shows the latest version.</p>

<h2>Contact</h2>
@if($contact)
    <p>Questions about this policy can be sent to GeoBytes at <a href="mailto:{{ $contact }}">{{ $contact }}</a>.</p>
@else
    <p>A contact email for privacy questions has not been published yet.</p>
@endif
@endsection
