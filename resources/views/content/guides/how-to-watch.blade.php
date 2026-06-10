@extends('layouts.content')

@section('content')
    <h1>How to watch the World Cup 2026 free</h1>
    <p class="lede">All 104 matches of the 2026 World Cup are shown free-to-air in some countries, and
        partly free in others. Pick your country for the free-to-air channels, free streams, and how
        much of the tournament is free.</p>

    <table>
        <thead>
            <tr><th>Country</th><th>Free-to-air</th><th>How much is free</th></tr>
        </thead>
        <tbody>
            @foreach (config('watch') as $slug => $market)
                <tr>
                    <td><a href="{{ url('/guides/how-to-watch-world-cup-2026-free/'.$slug) }}">{{ ucfirst($market['country']) }}</a></td>
                    <td>{{ $market['fta'] }}</td>
                    <td>{{ $market['free'] ? 'Free-to-air' : 'Partly free' }}</td>
                </tr>
            @endforeach
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
