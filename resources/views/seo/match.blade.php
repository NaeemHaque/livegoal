@php
    /** @var array<string, mixed> $match */
    $home = data_get($match, 'home.name');
    $away = data_get($match, 'away.name');
    $competition = data_get($match, 'competition.name');
    $venue = data_get($match, 'venue');
    $stage = data_get($match, 'stage');
    $status = data_get($match, 'status');
    $homeScore = data_get($match, 'homeScore');
    $awayScore = data_get($match, 'awayScore');
    $kickoff = data_get($match, 'kickoff');
    $kickoffHuman = $kickoff ? \Illuminate\Support\Carbon::parse($kickoff)->format('D j M Y, H:i').' UTC' : null;
    $competitionCode = data_get($match, 'competition.code');
    $homeUrl = \App\Seo\Slug::url('team', (string) data_get($match, 'home.id'), $home);
    $awayUrl = \App\Seo\Slug::url('team', (string) data_get($match, 'away.id'), $away);
    $isResult = in_array($status, ['FT', 'AET', 'PEN'], true);
    $isLive = in_array($status, ['LIVE', 'HT', 'ET'], true);

    // Build the factual summary in PHP (a clear, number-dense statement is the
    // validated GEO lever) rather than nested inline Blade conditionals.
    if ($isResult) {
        $summary = "Full time: {$home} {$homeScore}–{$awayScore} {$away}";
    } elseif ($isLive) {
        $summary = "Live: {$home} {$homeScore}–{$awayScore} {$away}";
    } else {
        $summary = "{$home} play {$away}";
    }

    if ($competition) {
        $summary .= " in the {$competition}";
    }

    if (! $isLive && $venue) {
        $summary .= " at {$venue}";
    }

    if (! $isLive && $kickoffHuman) {
        $summary .= $isResult ? ", {$kickoffHuman}" : ", kicking off {$kickoffHuman}";
    }

    $summary .= '.';
@endphp
<article data-seo-prerender>
    <h1>{{ $home }} vs {{ $away }}</h1>
    <p>{{ $summary }}</p>
    <dl>
        @if ($competition)
            <dt>Competition</dt>
            <dd>@if ($competitionCode)<a href="{{ url('/competition/'.$competitionCode) }}">{{ $competition }}</a>@else{{ $competition }}@endif</dd>
        @endif
        @if ($stage)<dt>Stage</dt><dd>{{ $stage }}</dd>@endif
        @if ($kickoffHuman)<dt>Kick-off</dt><dd>{{ $kickoffHuman }}</dd>@endif
        @if ($venue)<dt>Venue</dt><dd>{{ $venue }}</dd>@endif
        @if ($status)<dt>Status</dt><dd>{{ $status }}</dd>@endif
    </dl>
    <p>Team pages: <a href="{{ $homeUrl }}">{{ $home }}</a> · <a href="{{ $awayUrl }}">{{ $away }}</a></p>
    @isset($updatedAt)
        <p>Last updated {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('H:i, j M Y') }} UTC.</p>
    @endisset
</article>
