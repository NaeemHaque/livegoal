@extends('layouts.content')

@section('content')
    <h1>Football guides &amp; World Cup 2026 explainers</h1>
    <p class="lede">Plain-English answers to how the 2026 World Cup works, how to watch it free,
        and the football terms you'll hear along the way.</p>

    @foreach ($grouped as $group => $pages)
        <h2>{{ $group }}</h2>
        <ul>
            @foreach ($pages as $page)
                <li>
                    <a href="{{ url($page['path']) }}">{{ $page['title'] }}</a>
                    — {{ $page['description'] }}
                </li>
            @endforeach
        </ul>
    @endforeach
@endsection
