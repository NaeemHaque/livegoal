@php
    /** @var string $name */
    /** @var string $intro */
    /** @var list<array{key: string, label: string, rows: list<array<string, mixed>>}> $groups */
    /** @var list<array<string, mixed>> $scorers */
    /** @var list<array<string, mixed>> $fixtures */
@endphp
<article data-seo-prerender>
    <h1>{{ $name }}</h1>
    @if ($intro)<p>{{ $intro }}</p>@endif

    @foreach ($groups as $group)
        <section>
            <h2>{{ $group['label'] }}</h2>
            <table>
                <thead>
                    <tr><th>#</th><th>Team</th><th>P</th><th>W</th><th>D</th><th>L</th><th>GD</th><th>Pts</th></tr>
                </thead>
                <tbody>
                    @foreach ($group['rows'] as $row)
                        <tr>
                            <td>{{ data_get($row, 'position') }}</td>
                            <td><a href="{{ \App\Seo\Slug::url('team', (string) data_get($row, 'team.id'), data_get($row, 'team.name')) }}">{{ data_get($row, 'team.name') }}</a></td>
                            <td>{{ data_get($row, 'played') }}</td>
                            <td>{{ data_get($row, 'won') }}</td>
                            <td>{{ data_get($row, 'draw') }}</td>
                            <td>{{ data_get($row, 'lost') }}</td>
                            <td>{{ data_get($row, 'goalDifference') }}</td>
                            <td>{{ data_get($row, 'points') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach

    @if (count($scorers))
        <section>
            <h2>Top scorers</h2>
            <ol>
                @foreach ($scorers as $scorer)
                    @php
                        $playerUrl = \App\Seo\Slug::url('player', (string) data_get($scorer, 'player.id'), data_get($scorer, 'player.name'));
                        $suffix = data_get($scorer, 'team.name') ? ' ('.data_get($scorer, 'team.name').')' : '';
                        $suffix .= ' — '.data_get($scorer, 'goals').' goals';
                    @endphp
                    <li><a href="{{ $playerUrl }}">{{ data_get($scorer, 'player.name') }}</a>{{ $suffix }}</li>
                @endforeach
            </ol>
        </section>
    @endif

    @if (count($fixtures))
        <section>
            <h2>Upcoming fixtures</h2>
            <ul>
                @foreach ($fixtures as $fixture)
                    @php
                        $fixtureUrl = \App\Seo\Slug::url('match', (string) data_get($fixture, 'id'), data_get($fixture, 'home.name').' vs '.data_get($fixture, 'away.name'));
                        $line = data_get($fixture, 'home.name').' vs '.data_get($fixture, 'away.name');
                        if (data_get($fixture, 'kickoff')) {
                            $line .= ' — '.\Illuminate\Support\Carbon::parse(data_get($fixture, 'kickoff'))->format('j M, H:i').' UTC';
                        }
                    @endphp
                    <li><a href="{{ $fixtureUrl }}">{{ $line }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @isset($updatedAt)
        <p>Last updated {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('H:i, j M Y') }} UTC.</p>
    @endisset
</article>
