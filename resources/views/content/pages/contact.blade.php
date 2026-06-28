@extends('layouts.content')

@section('content')
    <h1>Contact LiveGoal</h1>
    <p class="lede">Found a wrong score, a missing match, or have a feature idea? We'd like to hear it.</p>

    @php($email = config('seo.contact_email'))

    <p>LiveGoal is open source, so the best way to report a problem or suggest a feature is to
        <a href="https://github.com/NaeemHaque/livegoal/issues/new" target="_blank" rel="noopener noreferrer">open
            an issue on GitHub</a>. To help us look into a data issue quickly, please include the match, team or
        competition and a link to the page.</p>

    @if ($email)
        <p>Prefer email? Reach us at <a href="mailto:{{ $email }}">{{ $email }}</a>.</p>
    @endif

    <h2>Common questions</h2>
    <h3>A score or result looks wrong</h3>
    <p>Scores come from our data provider and refresh about once a minute — see
        <a href="{{ url('/how-our-data-works') }}">how our data works</a>. If something still looks off
        after a few minutes,
        <a href="https://github.com/NaeemHaque/livegoal/issues/new" target="_blank" rel="noopener noreferrer">let
            us know which match</a>.</p>

    <h3>A competition or team is missing</h3>
    <p>LiveGoal covers the 2026 World Cup and major competitions — browse the full
        <a href="{{ url('/competitions') }}">competitions list</a> to see what's included.</p>

    <p>Learn more <a href="{{ url('/about') }}">about LiveGoal</a>.</p>
@endsection
