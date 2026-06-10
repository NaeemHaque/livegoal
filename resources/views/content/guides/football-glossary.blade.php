@extends('layouts.content')

@section('content')
    <h1>Football glossary: key terms explained</h1>
    <p class="lede">Plain-English definitions of the football terms you'll see across LiveGoal's
        scores, tables and match pages. Tap a term for the full explanation.</p>

    <ul>
        @foreach (config('glossary') as $slug => $term)
            <li>
                <a href="{{ url('/guides/'.$slug) }}"><strong>{{ $term['term'] }}</strong></a>
                — {{ $term['definition'] }}
            </li>
        @endforeach
    </ul>

    <p>Want the rules of the tournament itself? See <a href="{{ url('/guides/world-cup-2026-format-explained') }}">how
        the 2026 World Cup format works</a>.</p>
@endsection
