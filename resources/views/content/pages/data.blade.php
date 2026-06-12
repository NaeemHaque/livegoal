@extends('layouts.content')

@section('content')
    <h1>How LiveGoal's data works</h1>
    <p class="lede">LiveGoal aims to be a fast, trustworthy view of live football. Here's where the data
        comes from, how often it updates, and how we keep the site quick and ad-free.</p>

    <h2>Where the data comes from</h2>
    <p>Match, team, competition and player data is sourced from
        <a href="https://www.football-data.org" rel="nofollow noopener">football-data.org</a>, a
        football data provider. LiveGoal's server fetches and caches that data, so your browser never
        calls the provider directly.</p>

    <h2>How often scores update</h2>
    <p>A single scheduled poller on our server checks for live score changes about once a minute and
        updates the cache for everyone at once. Open pages refresh in the background, so live scores
        stay current without you reloading. Each entity page also shows a "last updated" time so you
        know how fresh the data is.</p>

    <h2>Why the site is fast</h2>
    <p>Because everything is served from cache rather than fetched per visitor, pages load quickly even
        during a busy matchday. Responses carry standard caching headers so browsers and content
        networks can serve them efficiently.</p>

    <h2>What we don't do</h2>
    <ul>
        <li>No betting odds or gambling advertising.</li>
        <li>No accounts, paywalls or tracking-heavy ads.</li>
    </ul>

    <h2>Coverage</h2>
    <p>LiveGoal covers the <a href="{{ url('/competition/WC') }}">FIFA World Cup 2026</a> and major
        competitions including the Champions League, Premier League, La Liga, Serie A, Bundesliga,
        Ligue 1 and more — see the full <a href="{{ url('/competitions') }}">competitions list</a>.</p>

    <p>Spotted something wrong? <a href="{{ url('/contact') }}">Let us know</a>.</p>
@endsection
