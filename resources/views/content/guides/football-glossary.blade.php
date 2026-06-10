@extends('layouts.content')

@section('content')
    <h1>Football glossary: key terms explained</h1>
    <p class="lede">Plain-English definitions of the football terms you'll see across LiveGoal's
        scores, tables and match pages.</p>

    <h2>Offside</h2>
    <p>An attacker is offside if they are nearer the opponents' goal line than both the ball and the
        second-last defender at the moment a team-mate plays the ball — and then become involved in
        the play. It results in a free-kick to the defending team.</p>

    <h2>Goal difference (GD)</h2>
    <p>Goals scored minus goals conceded. It's the first tiebreaker for teams level on points in a
        league table or World Cup group.</p>

    <h2>Clean sheet</h2>
    <p>When a team concedes no goals in a match — its goalkeeper and defence "keep a clean sheet".</p>

    <h2>Added time (stoppage time)</h2>
    <p>Extra minutes added by the referee at the end of each half to make up for time lost to
        substitutions, injuries and other stoppages.</p>

    <h2>Extra time</h2>
    <p>In a knockout match level after 90 minutes, two further 15-minute periods are played to try to
        find a winner before any penalty shootout.</p>

    <h2>Penalty shootout</h2>
    <p>Used to decide a knockout match still level after extra time. Teams take alternate penalty
        kicks — five each, then sudden death — until one side leads.</p>

    <h2>Group stage</h2>
    <p>The opening round-robin phase where teams in the same group each play one another, with the
        top finishers advancing. At the 2026 World Cup there are 12 groups of four.</p>

    <h2>Knockout stage</h2>
    <p>The single-elimination phase after the groups: the loser of each match is out. At the 2026
        World Cup it runs from the Round of 32 to the final.</p>

    <h2>Aggregate</h2>
    <p>The combined score across a two-legged tie (two matches, home and away). The team with the
        higher aggregate score advances.</p>

    <h2>Away goals rule</h2>
    <p>An old tiebreaker that favoured goals scored away from home in two-legged ties. Most major
        competitions, including UEFA's, have now abolished it.</p>

    <h2>VAR (Video Assistant Referee)</h2>
    <p>Officials who review the on-field referee's decisions on goals, penalties, red cards and
        mistaken identity using video replays.</p>

    <h2>Hat-trick</h2>
    <p>Three goals scored by the same player in a single match. Two goals is a "brace".</p>

    <h2>Own goal</h2>
    <p>A goal accidentally scored by a player into their own team's net; it counts for the opposition.</p>

    <p>Want the rules of the tournament itself? See <a href="{{ url('/guides/world-cup-2026-format-explained') }}">how
        the 2026 World Cup format works</a>.</p>
@endsection
