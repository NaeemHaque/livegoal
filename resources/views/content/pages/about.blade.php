@extends('layouts.content')

@section('content')
    <h1>About LiveGoal</h1>
    <p class="lede">LiveGoal is a free, fast football live-scores site — built around the 2026 World
        Cup and the world's major leagues, with no betting and no clutter.</p>

    <h2>What LiveGoal does</h2>
    <p>LiveGoal shows live scores, fixtures, results, league tables and knockout brackets in real
        time. You can follow the <a href="{{ url('/competition/WC') }}">2026 World Cup</a>, the
        <a href="{{ url('/competitions') }}">major leagues</a>, and individual teams and players —
        with <a href="{{ url('/scorers') }}">top-scorer</a> races and matchday fixtures all in one place.</p>

    <h2>What makes it different</h2>
    <ul>
        <li><strong>No betting, no gambling ads.</strong> Just the football.</li>
        <li><strong>Free and account-free.</strong> Nothing to sign up for or pay.</li>
        <li><strong>Fast and clean.</strong> Lightweight pages and real-time updates.</li>
    </ul>

    <h2>How it works</h2>
    <p>Scores and data refresh automatically while you watch. For where the numbers come from and how
        often they update, see <a href="{{ url('/how-our-data-works') }}">how our data works</a>.</p>

    <p>New to the tournament? Start with <a href="{{ url('/guides/world-cup-2026-format-explained') }}">how
        the 2026 World Cup format works</a>, or browse all <a href="{{ url('/guides') }}">football guides</a>.</p>
@endsection
