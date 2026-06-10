@extends('layouts.content')

@section('content')
    <h1>World Cup 2026 knockout bracket explained</h1>
    <p class="lede">After the group stage, 32 teams enter a single-elimination bracket. Win and you go
        through; lose and you're out. It runs from the new Round of 32 to the final on 19 July 2026.</p>

    <h2>What are the knockout rounds?</h2>
    <p>With 48 teams the 2026 World Cup adds a round at the start of the bracket:</p>
    <ul>
        <li><strong>Round of 32</strong> — 32 teams, 16 matches (new for 2026)</li>
        <li><strong>Round of 16</strong> — 16 teams, 8 matches</li>
        <li><strong>Quarter-finals</strong> — 8 teams, 4 matches</li>
        <li><strong>Semi-finals</strong> — 4 teams, 2 matches</li>
        <li><strong>Third-place play-off</strong> — the two losing semi-finalists</li>
        <li><strong>Final</strong> — at MetLife Stadium near New York, 19 July 2026</li>
    </ul>
    <p>Follow the live bracket as it fills in on the
        <a href="{{ url('/competition/WC') }}">World Cup 2026 hub</a>.</p>

    <h2>What happens if a knockout match is a draw?</h2>
    <p>Knockout matches must produce a winner. If the score is level after 90 minutes:</p>
    <ol>
        <li><strong>Extra time</strong> — two further periods of 15 minutes each.</li>
        <li><strong>Penalty shootout</strong> — if still level after extra time, each team takes
            penalties (best of five, then sudden death) to decide who advances.</li>
    </ol>
    <p>See the <a href="{{ url('/guides/football-glossary') }}">glossary</a> for extra time and penalty
        shootout definitions.</p>

    @isset($live)
        @include('content.partials.wc-live')
    @endisset

    <h2>How do teams reach the knockouts?</h2>
    <p>The top two from each of the 12 groups plus the eight best third-placed teams qualify — read
        <a href="{{ url('/guides/world-cup-2026-groups-and-qualification') }}">how teams qualify</a>,
        or the overall <a href="{{ url('/guides/world-cup-2026-format-explained') }}">2026 World Cup
        format</a>.</p>
@endsection
