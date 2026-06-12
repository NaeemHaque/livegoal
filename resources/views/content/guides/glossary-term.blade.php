@extends('layouts.content')

@section('content')
    <h1>{{ $term['question'] }}</h1>
    <p class="lede">{{ $term['definition'] }}</p>

    @if (! empty($term['detail']))
        <p>{{ $term['detail'] }}</p>
    @endif

    @if (! empty($term['link']))
        <p><a href="{{ url($term['link']['to']) }}">{{ $term['link']['label'] }}</a></p>
    @endif

    <h2>More football terms</h2>
    <ul>
        @foreach (config('glossary') as $slug => $other)
            @if (($other['title'] ?? '') !== ($term['title'] ?? ''))
                <li><a href="{{ url('/guides/'.$slug) }}">{{ $other['question'] }}</a></li>
            @endif
        @endforeach
    </ul>

    <p><a href="{{ url('/guides/football-glossary') }}">Back to the football glossary</a></p>
@endsection
