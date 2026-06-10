@php
    /** @var array<string, mixed> $team */
    /** @var list<array<string, mixed>> $upcoming */
    /** @var list<array<string, mixed>> $recent */
    $name = data_get($team, 'name');
    $area = data_get($team, 'area.name');
    $venue = data_get($team, 'venue');
    $founded = data_get($team, 'founded');

    $summary = $name;
    if ($area) {
        $summary .= " is a football club from {$area}";
    }
    if ($founded) {
        $summary .= ", founded {$founded}";
    }
    $summary .= '. Fixtures, results, squad and live scores on LiveGoal.';
@endphp
<article data-seo-prerender>
    <h1>{{ $name }}</h1>
    <p>{{ $summary }}</p>
    <dl>
        @if ($area)<dt>Country</dt><dd>{{ $area }}</dd>@endif
        @if ($venue)<dt>Stadium</dt><dd>{{ $venue }}</dd>@endif
        @if ($founded)<dt>Founded</dt><dd>{{ $founded }}</dd>@endif
    </dl>

    @if (count($upcoming))
        <section>
            <h2>Upcoming fixtures</h2>
            <ul>
                @foreach ($upcoming as $match)
                    @php
                        $line = data_get($match, 'home.name').' vs '.data_get($match, 'away.name');
                        if (data_get($match, 'kickoff')) {
                            $line .= ' — '.\Illuminate\Support\Carbon::parse(data_get($match, 'kickoff'))->format('j M, H:i').' UTC';
                        }
                    @endphp
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (count($recent))
        <section>
            <h2>Recent results</h2>
            <ul>
                @foreach ($recent as $match)
                    <li>{{ data_get($match, 'home.name') }} {{ data_get($match, 'homeScore') }}–{{ data_get($match, 'awayScore') }} {{ data_get($match, 'away.name') }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @isset($updatedAt)
        <p>Last updated {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('H:i, j M Y') }} UTC.</p>
    @endisset
</article>
