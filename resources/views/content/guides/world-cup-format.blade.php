@extends('layouts.content')

@section('content')
    <h1>World Cup 2026 format explained</h1>
    <p class="lede">The 2026 FIFA World Cup is the first with <strong>48 teams</strong>. It runs from
        <strong>11 June to 19 July 2026</strong> across 16 host cities in the United States, Canada and
        Mexico, and for the first time features <strong>104 matches</strong>.</p>

    <h2>How many teams and matches are there?</h2>
    <p>48 teams play 104 matches over 39 days — up from 32 teams and 64 matches at Qatar 2022. The
        expansion adds a brand-new knockout round (the Round of 32) before the familiar last 16.</p>

    <h2>How does the group stage work?</h2>
    <p>The 48 teams are split into <strong>12 groups of four</strong> (Groups A to L). Each team plays
        the other three in its group once, so every team is guaranteed three matches. You can follow
        the live group tables on the <a href="{{ url('/competition/WC') }}">World Cup 2026 hub</a>.</p>
    <p>The <strong>top two teams from each group</strong> advance automatically — that's 24 teams —
        plus the <strong>eight best third-placed teams</strong> across all groups, making 32 for the
        knockout stage. See <a href="{{ url('/guides/world-cup-2026-groups-and-qualification') }}">how
        teams qualify and the tiebreakers</a> for the full detail.</p>

    <h2>What happens in the knockout stage?</h2>
    <p>The 32 qualifiers enter a single-elimination bracket: <strong>Round of 32 → Round of 16 →
        quarter-finals → semi-finals → final</strong>, plus a third-place play-off. The final is on
        <strong>19 July 2026 at MetLife Stadium</strong> near New York. Read the
        <a href="{{ url('/guides/world-cup-2026-knockout-bracket-explained') }}">knockout bracket
        explainer</a> for how extra time and penalties decide tied matches.</p>

    <h2>Where is the 2026 World Cup held?</h2>
    <p>It's the first World Cup hosted by three countries, across 16 cities — 11 in the USA and three
        in Mexico and two in Canada. The opening match is in Mexico City and the final is in the USA.</p>

    @isset($live)
        @include('content.partials.wc-live')
    @endisset

    <p>Follow every fixture and result as it happens on <a href="{{ url('/') }}">LiveGoal's live
        scores</a>, or browse the <a href="{{ url('/matches') }}">full fixtures by date</a>.</p>
@endsection
