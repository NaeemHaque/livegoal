@extends('layouts.content')

@section('content')
    <h1>Privacy Policy</h1>
    <p class="lede">LiveGoal is a free, ad-free football scores site with no accounts and no sign-up.
        We collect as little as possible — here is exactly what that means.</p>

    <h2>No accounts, no personal data</h2>
    <p>There is nothing to register for and no login. We don't ask for your name, email or any
        personal details, and we don't build a profile of you.</p>

    <h2>What's stored on your device</h2>
    <p>Your preferences — followed teams and competitions, theme, timezone and refresh settings — are
        saved in your browser's local storage. They stay on your device and are never sent to us. Clearing
        your browser data removes them.</p>

    <h2>Push notifications (optional)</h2>
    <p>If you turn on match alerts, your browser creates an anonymous push subscription that we store only
        to deliver those notifications — goals, kick-offs and full-time scores for the teams you follow. It
        contains no personal information, and turning notifications off removes it. Expired subscriptions are
        deleted automatically.</p>

    <h2>Analytics</h2>
    <p>To understand which pages are popular and keep the site fast, we use privacy-friendly,
        cookieless analytics (<a href="https://plausible.io" target="_blank" rel="noopener noreferrer">Plausible</a>),
        and we may use Google Analytics. These report aggregate traffic only — never who you are. Plausible
        sets no cookies; if Google Analytics is enabled it may set its own analytics cookies.</p>

    <h2>Where match data comes from</h2>
    <p>Scores, fixtures and tables come from third-party football data providers, principally
        <a href="https://www.football-data.org" target="_blank" rel="noopener noreferrer">football-data.org</a>.
        See <a href="{{ url('/how-our-data-works') }}">how our data works</a> for details. Visiting the site
        sends a normal web request (including your IP address) to our server and these providers, as any
        website does.</p>

    <h2>What we don't do</h2>
    <ul>
        <li><strong>No betting or gambling ads</strong>, and none of the tracking that comes with them.</li>
        <li><strong>No selling or sharing</strong> of data with advertisers.</li>
        <li><strong>No cross-site ad tracking.</strong></li>
    </ul>

    <h2>Changes &amp; contact</h2>
    <p>If this policy changes, the updated version will appear here. Questions about privacy? Reach us via the
        <a href="{{ url('/contact') }}">contact page</a>.</p>

    <p><em>Last updated June 2026.</em></p>
@endsection
