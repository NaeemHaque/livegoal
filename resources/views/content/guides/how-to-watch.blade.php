@extends('layouts.content')

@section('content')
    <h1>How to watch the World Cup 2026 free</h1>
    <p class="lede">All 104 matches of the 2026 World Cup are shown free-to-air in some countries, and
        partly free in others. Here's where to watch free in major markets — and how much of the
        tournament is included.</p>

    <table>
        <thead>
            <tr><th>Country</th><th>Free-to-air</th><th>How much is free</th></tr>
        </thead>
        <tbody>
            <tr><td>United Kingdom</td><td>BBC &amp; ITV (BBC iPlayer, ITVX)</td><td>All 104 matches free</td></tr>
            <tr><td>Australia</td><td>SBS (SBS On Demand)</td><td>All 104 matches free</td></tr>
            <tr><td>Mexico</td><td>TV Azteca &amp; TelevisaUnivision (Canal 5)</td><td>Free nationwide</td></tr>
            <tr><td>USA (English)</td><td>FOX / FS1 (over-the-air)</td><td>Most matches free OTA; all 104 on paid FOX One</td></tr>
            <tr><td>USA (Spanish)</td><td>Telemundo (over-the-air)</td><td>92 of 104 free; all 104 on paid Peacock</td></tr>
            <tr><td>Germany</td><td>ARD &amp; ZDF</td><td>~60 of 104 free; rest on paid MagentaTV</td></tr>
            <tr><td>France</td><td>M6</td><td>~54 matches free; rest on beIN Sports (paid)</td></tr>
            <tr><td>Brazil</td><td>TV Globo &amp; CazéTV (YouTube)</td><td>Selected matches free; rest on pay TV</td></tr>
            <tr><td>Canada</td><td>CTV</td><td>Marquee games free (opener, Canada matches, final); all 104 on paid TSN/RDS</td></tr>
            <tr><td>India</td><td>DD Sports (DD Free Dish)</td><td>Opener, quarter-finals, semi-finals &amp; final free; rest on paid Zee/ZEE5</td></tr>
        </tbody>
    </table>

    <p class="note">Broadcast rights vary by country and can change, and some broadcasters split
        matches between free and pay tiers. Always check your local listings before kick-off.
        Last reviewed June 2026.</p>

    <h2>Free streaming options</h2>
    <p>In several regions, selected matches also stream free on the official rights holder's app
        (for example BBC iPlayer, ITVX, SBS On Demand or TV Azteca's app) and, where available,
        on FIFA's own platform and approved YouTube channels.</p>

    <h2>What time do matches kick off?</h2>
    <p>Kick-off times depend on the host city's time zone (matches are played across the USA, Canada
        and Mexico). Each match page on LiveGoal shows the kick-off in UTC — check
        <a href="{{ url('/') }}">today's live scores</a> or the
        <a href="{{ url('/matches') }}">full fixtures by date</a> for the schedule.</p>

    <p>New to the tournament? Read <a href="{{ url('/guides/world-cup-2026-format-explained') }}">how the
        2026 World Cup format works</a>.</p>
@endsection
