@extends('layouts.content')

@section('content')
    <h1>How to watch the World Cup 2026 free in {{ $watch['country'] }}</h1>
    <p class="lede">
        @if ($watch['free'])
            Yes — you can watch the 2026 World Cup free in {{ $watch['country'] }}.
        @else
            Some matches are free in {{ $watch['country'] }}, but not all.
        @endif
        {{ $watch['scope'] }}
    </p>

    <h2>Free-to-air channels</h2>
    <p>In {{ $watch['country'] }}, the 2026 World Cup is shown free-to-air on
        <strong>{{ $watch['fta'] }}</strong>. {{ $watch['scope'] }}</p>

    @if (! empty($watch['streaming']))
        <h2>Free streaming</h2>
        <p>{{ $watch['streaming'] }}</p>
    @endif

    @if (! empty($watch['paid']))
        <h2>How to watch every match</h2>
        <p>{{ $watch['paid'] }}</p>
    @endif

    <h2>What time do matches kick off?</h2>
    <p>Kick-off times depend on the host city's time zone. Each match page on LiveGoal shows the
        kick-off in UTC — check <a href="{{ url('/') }}">today's live scores</a> or the
        <a href="{{ url('/matches') }}">full fixtures by date</a>.</p>

    <p class="note">Broadcast rights can change and some broadcasters split matches between free and
        pay tiers — always check your local listings. Last reviewed June 2026.</p>

    <p>See how to watch free in <a href="{{ url('/guides/how-to-watch-world-cup-2026-free') }}">other
        countries</a>, or read <a href="{{ url('/guides/world-cup-2026-format-explained') }}">how the
        2026 World Cup works</a>.</p>
@endsection
