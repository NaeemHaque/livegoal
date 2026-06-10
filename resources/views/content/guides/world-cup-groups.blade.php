@extends('layouts.content')

@section('content')
    <h1>World Cup 2026 groups &amp; how teams qualify</h1>
    <p class="lede">The 48 teams are drawn into <strong>12 groups of four</strong> (A–L). Each team
        plays three group matches, and 32 of the 48 advance to the knockout stage.</p>

    <h2>Who advances from each group?</h2>
    <p>The <strong>top two teams in every group</strong> qualify automatically — 24 teams in total.
        The remaining eight knockout places go to the <strong>eight best third-placed teams</strong>
        ranked across all 12 groups. That makes 32 teams for the new Round of 32.</p>
    <p>You can watch the live standings — including each team's qualification or third-place position —
        on the <a href="{{ url('/competition/WC') }}">World Cup 2026 table</a>.</p>

    <h2>How are the third-placed teams ranked?</h2>
    <p>The 12 third-placed teams are compared by their full group record: points first, then goal
        difference, then goals scored. The top eight go through; the bottom four are eliminated.</p>

    <h2>How are tied teams separated in a group?</h2>
    <p>If two or more teams finish level on points, FIFA applies these tiebreakers <strong>in order</strong>:</p>
    <ol>
        <li>Goal difference in all group matches</li>
        <li>Goals scored in all group matches</li>
        <li>Points in the matches between the tied teams</li>
        <li>Goal difference in the matches between the tied teams</li>
        <li>Goals scored in the matches between the tied teams</li>
        <li>Fair-play points (fewer yellow and red cards)</li>
        <li>Drawing of lots by FIFA</li>
    </ol>
    <p>New to the terms? The <a href="{{ url('/guides/football-glossary') }}">football glossary</a>
        explains goal difference, points and more.</p>

    <h2>What comes after the groups?</h2>
    <p>The 32 qualifiers enter a single-elimination bracket. See the
        <a href="{{ url('/guides/world-cup-2026-knockout-bracket-explained') }}">knockout bracket
        explainer</a>, or read the full <a href="{{ url('/guides/world-cup-2026-format-explained') }}">2026
        World Cup format</a>.</p>
@endsection
