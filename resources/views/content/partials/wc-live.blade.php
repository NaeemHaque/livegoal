@php
    /** @var array{groups: list<array{key: string, label: string, rows: list<array<string, mixed>>}>, fixtures: list<array<string, mixed>>, updatedAt: string|null} $live */
    $leaders = [];
    foreach ($live['groups'] as $group) {
        $top = $group['rows'][0] ?? null;
        if (is_array($top) && data_get($top, 'team.name')) {
            $leaders[] = ['group' => $group['label'], 'team' => data_get($top, 'team.name'), 'points' => data_get($top, 'points')];
        }
    }
@endphp

@if (count($live['fixtures']) || count($leaders))
    <h2>The World Cup 2026, live right now</h2>
    <p class="note">This section updates automatically from LiveGoal's live data — see the full
        <a href="{{ url('/competition/WC') }}">World Cup 2026 table, fixtures and results</a>.</p>

    @if (count($live['fixtures']))
        <h3>Next fixtures</h3>
        <ul>
            @foreach ($live['fixtures'] as $match)
                @php
                    $home = data_get($match, 'home.name');
                    $away = data_get($match, 'away.name');
                    $url = \App\Seo\Slug::url('match', (string) data_get($match, 'id'), $home.' vs '.$away);
                    $line = $home.' vs '.$away;
                    if (data_get($match, 'kickoff')) {
                        $line .= ' — '.\Illuminate\Support\Carbon::parse(data_get($match, 'kickoff'))->format('j M, H:i').' UTC';
                    }
                @endphp
                <li><a href="{{ $url }}">{{ $line }}</a></li>
            @endforeach
        </ul>
    @endif

    @if (count($leaders))
        <h3>Group leaders</h3>
        <table>
            <thead><tr><th>Group</th><th>Leader</th><th>Pts</th></tr></thead>
            <tbody>
                @foreach ($leaders as $leader)
                    <tr><td>{{ $leader['group'] }}</td><td>{{ $leader['team'] }}</td><td>{{ $leader['points'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($live['updatedAt'])
        <p class="note">Standings updated {{ \Illuminate\Support\Carbon::parse($live['updatedAt'])->format('H:i, j M Y') }} UTC.</p>
    @endif
@endif
