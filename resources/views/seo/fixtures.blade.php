@php
    /** @var string $heading */
    /** @var list<array<string, mixed>> $today */
    /** @var list<array<string, mixed>> $upcoming */

    $line = function (array $match): string {
        $home = data_get($match, 'home.name');
        $away = data_get($match, 'away.name');
        $status = data_get($match, 'status');
        $competition = data_get($match, 'competition.name');

        if (in_array($status, ['FT', 'AET', 'PEN', 'LIVE', 'HT', 'ET'], true)) {
            $text = $home.' '.data_get($match, 'homeScore').'–'.data_get($match, 'awayScore').' '.$away;
        } else {
            $text = $home.' vs '.$away;
        }

        if ($competition) {
            $text .= ' — '.$competition;
        }

        if ($status === 'SCHEDULED' && data_get($match, 'kickoff')) {
            $text .= ', '.\Illuminate\Support\Carbon::parse(data_get($match, 'kickoff'))->format('H:i').' UTC';
        }

        return $text;
    };
@endphp
<article data-seo-prerender>
    <h1>{{ $heading }}</h1>
    <p>Free, real-time football scores, fixtures and results across the FIFA World Cup 2026
        and major leagues — no betting ads.</p>

    @if (count($today))
        <section>
            <h2>{{ $matchesHeading ?? "Today's matches" }}</h2>
            <ul>
                @foreach ($today as $match)
                    <li>{{ $line($match) }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (count($upcoming))
        <section>
            <h2>Upcoming fixtures</h2>
            <ul>
                @foreach ($upcoming as $match)
                    <li>{{ $line($match) }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @isset($updatedAt)
        <p>Last updated {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('H:i, j M Y') }} UTC.</p>
    @endisset
</article>
