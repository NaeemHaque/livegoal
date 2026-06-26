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

    $matchUrl = function (array $match): string {
        $name = data_get($match, 'home.name').' vs '.data_get($match, 'away.name');

        return \App\Seo\Slug::url('match', (string) data_get($match, 'id'), $name);
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
                    <li><a href="{{ $matchUrl($match) }}">{{ $line($match) }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (count($upcoming))
        <section>
            <h2>Upcoming fixtures</h2>
            <ul>
                @foreach ($upcoming as $match)
                    <li><a href="{{ $matchUrl($match) }}">{{ $line($match) }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @isset($updatedAt)
        <p>Last updated {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('H:i, j M Y') }} UTC.</p>
    @endisset

    {{-- Crawlable link graph to the World Cup money pages (purely additive for
         crawlers — Vue replaces this body on mount). --}}
    <nav aria-label="World Cup 2026">
        <h2>World Cup 2026</h2>
        <ul>
            <li><a href="{{ url('/competition/WC') }}">World Cup 2026 scores, groups &amp; bracket</a></li>
            <li><a href="{{ url('/scorers') }}">World Cup 2026 top scorers</a></li>
            <li><a href="{{ url('/guides/world-cup-2026-format-explained') }}">How the World Cup 2026 format works</a></li>
            <li><a href="{{ url('/guides/world-cup-2026-knockout-bracket-explained') }}">World Cup 2026 knockout bracket</a></li>
        </ul>
    </nav>
</article>
