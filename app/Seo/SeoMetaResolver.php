<?php

namespace App\Seo;

use App\Services\Football\FeaturedMatches;
use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Resolves a per-URL SeoMeta from cached football data for the SPA shell.
 *
 * Reads are cache-only via FootballData::peek — crawler traffic never reaches
 * the rate-limited upstream API. A real, cached entity yields a rich, indexable
 * page (title, description, Open Graph, JSON-LD); an unresolved entity yields a
 * generic noindex page so junk IDs never enter the index.
 */
class SeoMetaResolver
{
    public function __construct(
        private readonly FootballData $football,
        private readonly Normalizer $normalizer,
        private readonly FeaturedMatches $featured,
    ) {}

    public function home(): SeoMeta
    {
        return new SeoMeta(
            title: Config::string('seo.default_title'),
            description: Config::string('seo.default_description'),
            canonical: url('/'),
            jsonLd: $this->siteJsonLd(),
        );
    }

    public function matches(): SeoMeta
    {
        return $this->hub(
            'Football Fixtures & Results by Date',
            'Browse football fixtures and results by date across the World Cup 2026, Premier '
                .'League, La Liga, Serie A and Europe\'s top leagues. Live scores on LiveGoal.',
            'Matches',
        );
    }

    public function competitions(): SeoMeta
    {
        return $this->hub(
            'Football Competitions & Leagues',
            'All competitions on LiveGoal: FIFA World Cup 2026, UEFA Champions League, Premier '
                .'League, La Liga, Serie A, Bundesliga, Ligue 1 and more — tables, fixtures and results.',
            'Competitions',
        );
    }

    public function scorers(): SeoMeta
    {
        return $this->hub(
            'Top Scorers & Golden Boot Race',
            'Top scorers and Golden Boot races across the Premier League, La Liga, Serie A, '
                .'Bundesliga and more — goals, assists and penalties, updated live on LiveGoal.',
            'Top Scorers',
        );
    }

    /**
     * A specific day's fixtures page (/matches/{Y-m-d}) — targets the high-volume
     * "football matches on {date}" long tail. Indexable only when that day has
     * fixtures, so empty future/past dates don't create thin pages.
     */
    public function matchesForDate(string $date): SeoMeta
    {
        $canonical = url('/matches/'.$date);
        $human = $this->humanDate($date);
        $hasFixtures = $this->datedMatches($date) !== [];

        return new SeoMeta(
            title: $this->brand("Football Matches on {$human} — Live Scores & Results"),
            description: "All football fixtures and results on {$human}: World Cup 2026, Premier "
                .'League, La Liga, Serie A and Europe\'s top leagues. Live scores on LiveGoal.',
            canonical: $canonical,
            robots: $hasFixtures ? 'index,follow' : 'noindex,follow',
            jsonLd: [
                $this->breadcrumb([
                    [Config::string('seo.site_name'), url('/')],
                    ['Matches', url('/matches')],
                    [$human, $canonical],
                ]),
            ],
        );
    }

    public function match(string $id): SeoMeta
    {
        $numericId = Slug::id($id);
        $raw = $this->football->peek("match:{$numericId}");

        if ($raw === null) {
            return $this->cold('Match Centre — Live Football Score', 'Live football scores, lineups and results on LiveGoal.', URL::current());
        }

        $m = $this->normalizer->match($raw);
        $home = $this->str(data_get($m, 'home.name'));
        $away = $this->str(data_get($m, 'away.name'));

        if ($home === '' || $away === '') {
            return $this->cold('Match Centre — Live Football Score', 'Live football scores, lineups and results on LiveGoal.', URL::current());
        }

        $canonical = Slug::url('match', $numericId, "{$home} vs {$away}");
        $competition = $this->nullableStr(data_get($m, 'competition.name'));
        $status = $this->str(data_get($m, 'status'));
        $venue = $this->nullableStr(data_get($m, 'venue'));
        $kickoff = $this->nullableStr(data_get($m, 'kickoff'));

        $title = $this->brand("{$home} vs {$away} — Live Score & Result");
        $description = $this->matchDescription($home, $away, $status, $competition, $venue, $kickoff, $this->int(data_get($m, 'homeScore')), $this->int(data_get($m, 'awayScore')));

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $canonical,
            jsonLd: [
                $this->sportsEvent($home, $away, $status, $competition, $venue, $kickoff, $canonical),
                $this->breadcrumb(array_values(array_filter([
                    [Config::string('seo.site_name'), url('/')],
                    $competition !== null ? [$competition, url('/matches')] : null,
                    ["{$home} vs {$away}", $canonical],
                ]))),
            ],
        );
    }

    public function competition(string $id): SeoMeta
    {
        $canonical = URL::current();
        $c = $this->resolveCompetition($id);

        if ($c === null) {
            return $this->cold('Competition — Table, Fixtures & Results', 'Football tables, fixtures, results and top scorers on LiveGoal.', $canonical);
        }

        $name = $this->str(data_get($c, 'name'));
        $isCup = $this->str(data_get($c, 'kind')) === 'cup';

        $title = $this->brand($isCup ? "{$name} — Fixtures, Results & Bracket" : "{$name} — Table, Fixtures & Results");
        $description = sprintf(
            'Live %s scores, %s, fixtures, results and top scorers. Follow every matchday on LiveGoal — free, no betting ads.',
            $name,
            $isCup ? 'groups and knockout bracket' : 'the full table',
        );

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $canonical,
            jsonLd: [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'SportsOrganization',
                    'name' => $name,
                    'sport' => 'Soccer',
                    'url' => $canonical,
                ],
                $this->breadcrumb([
                    [Config::string('seo.site_name'), url('/')],
                    ['Competitions', url('/competitions')],
                    [$name, $canonical],
                ]),
            ],
        );
    }

    public function team(string $id): SeoMeta
    {
        $numericId = Slug::id($id);
        $raw = $this->football->peek("team:{$numericId}");

        if ($raw === null) {
            return $this->cold('Team — Fixtures, Results & Squad', 'Football team fixtures, results, squad and live scores on LiveGoal.', URL::current());
        }

        $t = $this->normalizer->teamDetail($raw);
        $name = $this->str(data_get($t, 'name'));

        if ($name === '') {
            return $this->cold('Team — Fixtures, Results & Squad', 'Football team fixtures, results, squad and live scores on LiveGoal.', URL::current());
        }

        $canonical = Slug::url('team', $numericId, $name);
        $area = $this->nullableStr(data_get($t, 'area.name'));
        $crest = $this->nullableStr(data_get($t, 'crest'));

        $team = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsTeam',
            'name' => $name,
            'sport' => 'Soccer',
            'url' => $canonical,
        ];

        if ($crest !== null) {
            $team['logo'] = $crest;
        }

        return new SeoMeta(
            title: $this->brand("{$name} — Fixtures, Results & Squad"),
            description: sprintf(
                '%s fixtures, results, squad and live scores%s. Follow %s on LiveGoal — free, no betting ads.',
                $name,
                $area !== null ? " from {$area}" : '',
                $name,
            ),
            canonical: $canonical,
            jsonLd: [
                $team,
                $this->breadcrumb([
                    [Config::string('seo.site_name'), url('/')],
                    [$name, $canonical],
                ]),
            ],
        );
    }

    public function player(string $id): SeoMeta
    {
        $numericId = Slug::id($id);
        $raw = $this->football->peek("person:{$numericId}");

        if ($raw === null) {
            return $this->cold('Player Profile', 'Football player profiles, positions and teams on LiveGoal.', URL::current());
        }

        $p = $this->normalizer->person($raw);
        $name = $this->str(data_get($p, 'name'));

        if ($name === '') {
            return $this->cold('Player Profile', 'Football player profiles, positions and teams on LiveGoal.', URL::current());
        }

        $canonical = Slug::url('player', $numericId, $name);
        $position = $this->nullableStr(data_get($p, 'position'));
        $teamName = $this->nullableStr(data_get($p, 'team.name'));
        $nationality = $this->nullableStr(data_get($p, 'nationality'));

        $titleSuffix = $this->str(implode(', ', array_filter([$position, $teamName])));

        $person = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $name,
            'jobTitle' => $position ?? 'Footballer',
            'url' => $canonical,
        ];

        if ($nationality !== null) {
            $person['nationality'] = $nationality;
        }

        if ($teamName !== null) {
            $person['affiliation'] = ['@type' => 'SportsTeam', 'name' => $teamName];
        }

        return new SeoMeta(
            title: $this->brand($titleSuffix !== '' ? "{$name} — {$titleSuffix}" : $name),
            description: sprintf(
                '%s%s%s%s. Profile, team and career info on LiveGoal.',
                $name,
                $position !== null ? ", {$position}" : '',
                $teamName !== null ? " for {$teamName}" : '',
                $nationality !== null ? ", {$nationality} international" : '',
            ),
            canonical: $canonical,
            ogType: 'profile',
            jsonLd: [
                $person,
                $this->breadcrumb([
                    [Config::string('seo.site_name'), url('/')],
                    [$name, $canonical],
                ]),
            ],
        );
    }

    /**
     * Utility pages (settings/favorites/search) — useful to users, not to search
     * engines, so they are noindex,follow with a plain title.
     */
    public function utility(string $page): SeoMeta
    {
        $title = match ($page) {
            'favorites' => 'Following — Your Teams & Competitions',
            'search' => 'Search Teams & Competitions',
            default => 'Settings',
        };

        return new SeoMeta(
            title: $this->brand($title),
            description: Config::string('seo.default_description'),
            canonical: URL::current(),
            robots: 'noindex,follow',
        );
    }

    public function notFound(): SeoMeta
    {
        return new SeoMeta(
            title: $this->brand('Page Not Found'),
            description: 'The page you were looking for could not be found on LiveGoal.',
            canonical: URL::current(),
            robots: 'noindex,follow',
        );
    }

    // --- Prerendered body content ------------------------------------------
    //
    // AI answer crawlers don't run JavaScript, so the *facts* must be in the raw
    // HTML, not just the <head>. These return a Blade partial + data that the
    // shell renders inside #app; Vue replaces it on mount (same data = content
    // parity, not cloaking). Reads are cache-only (peek) — never hit upstream.

    /**
     * @return array{view: string, data: array<string, mixed>}|null
     */
    public function matchBody(string $id): ?array
    {
        $entry = $this->football->peekEntry('match:'.Slug::id($id));

        if ($entry === null) {
            return null;
        }

        $m = $this->normalizer->match($entry['data']);

        if ($this->str(data_get($m, 'home.name')) === '' || $this->str(data_get($m, 'away.name')) === '') {
            return null;
        }

        return ['view' => 'seo.match', 'data' => ['match' => $m, 'updatedAt' => $entry['at']]];
    }

    /**
     * @return array{view: string, data: array<string, mixed>}|null
     */
    public function competitionBody(string $id): ?array
    {
        $c = $this->resolveCompetition($id);

        if ($c === null) {
            return null;
        }

        $name = $this->str(data_get($c, 'name'));
        $isCup = $this->str(data_get($c, 'kind')) === 'cup';

        $standingsEntry = $this->football->peekEntry("standings:{$id}");
        $groups = $standingsEntry !== null ? $this->normalizer->standings($standingsEntry['data'])['groups'] : [];

        $scorersRaw = $this->football->peek("competition:{$id}:scorers");
        $scorers = $scorersRaw !== null ? array_slice($this->normalizer->scorers($scorersRaw), 0, 5) : [];

        $matchesRaw = $this->football->peek("competition:{$id}:matches");
        $fixtures = $matchesRaw !== null
            ? $this->upcoming($this->normalizer->matches($matchesRaw), 5)
            : [];

        return [
            'view' => 'seo.competition',
            'data' => [
                'name' => $name,
                'intro' => $this->competitionIntro($name, $isCup, $groups),
                'groups' => $groups,
                'scorers' => $scorers,
                'fixtures' => $fixtures,
                'updatedAt' => $standingsEntry['at'] ?? null,
            ],
        ];
    }

    /**
     * @return array{view: string, data: array<string, mixed>}|null
     */
    public function teamBody(string $id): ?array
    {
        $numericId = Slug::id($id);
        $entry = $this->football->peekEntry("team:{$numericId}");

        if ($entry === null) {
            return null;
        }

        $t = $this->normalizer->teamDetail($entry['data']);

        if ($this->str(data_get($t, 'name')) === '') {
            return null;
        }

        $matchesRaw = $this->football->peek("team:{$numericId}:matches");
        $all = $matchesRaw !== null ? $this->normalizer->matches($matchesRaw) : [];

        return [
            'view' => 'seo.team',
            'data' => [
                'team' => $t,
                'upcoming' => $this->upcoming($all, 5),
                'recent' => $this->recent($all, 5),
                'updatedAt' => $entry['at'],
            ],
        ];
    }

    /**
     * Today's and upcoming featured fixtures for the home / matches pages — the
     * highest-value "matches today" surface during a live tournament. Cache-only
     * (crawler-safe): never triggers an upstream fetch.
     *
     * @return array{view: string, data: array<string, mixed>}|null
     */
    public function fixturesBody(string $heading): ?array
    {
        $agg = $this->featured->all(allowFetch: false);

        if (! $agg['served']) {
            return null;
        }

        $today = Carbon::now()->toDateString();
        $todayMatches = $this->featured->onDate($agg['matches'], $today);
        $upcoming = $this->featured->scheduledFrom($agg['matches'], $today, 10);

        if ($todayMatches === [] && $upcoming === []) {
            return null;
        }

        return [
            'view' => 'seo.fixtures',
            'data' => [
                'heading' => $heading,
                'matchesHeading' => "Today's matches",
                'today' => $todayMatches,
                'upcoming' => $upcoming,
                'updatedAt' => $agg['lastUpdated'],
            ],
        ];
    }

    /**
     * Prerendered fixtures for a specific date page.
     *
     * @return array{view: string, data: array<string, mixed>}|null
     */
    public function fixturesBodyForDate(string $date): ?array
    {
        $agg = $this->featured->all(allowFetch: false);
        $matches = $this->featured->onDate($agg['matches'], $date);

        if ($matches === []) {
            return null;
        }

        $human = $this->humanDate($date);

        return [
            'view' => 'seo.fixtures',
            'data' => [
                'heading' => "Football Matches — {$human}",
                'matchesHeading' => 'Fixtures & results',
                'today' => $matches,
                'upcoming' => [],
                'updatedAt' => $agg['lastUpdated'],
            ],
        ];
    }

    /**
     * @return array{view: string, data: array<string, mixed>}|null
     */
    public function playerBody(string $id): ?array
    {
        $entry = $this->football->peekEntry('person:'.Slug::id($id));

        if ($entry === null) {
            return null;
        }

        $p = $this->normalizer->person($entry['data']);

        if ($this->str(data_get($p, 'name')) === '') {
            return null;
        }

        return ['view' => 'seo.player', 'data' => ['player' => $p, 'updatedAt' => $entry['at']]];
    }

    /**
     * Next scheduled fixtures, soonest first.
     *
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    private function upcoming(array $matches, int $limit): array
    {
        $upcoming = array_values(array_filter($matches, fn (array $m): bool => $this->str(data_get($m, 'status')) === 'SCHEDULED'));
        usort($upcoming, fn (array $a, array $b): int => strcmp($this->str(data_get($a, 'kickoff')), $this->str(data_get($b, 'kickoff'))));

        return array_slice($upcoming, 0, $limit);
    }

    /**
     * Most recent finished results, newest first.
     *
     * @param  list<array<string, mixed>>  $matches
     * @return list<array<string, mixed>>
     */
    private function recent(array $matches, int $limit): array
    {
        $finished = array_values(array_filter($matches, fn (array $m): bool => $this->str(data_get($m, 'status')) === 'FT'));
        usort($finished, fn (array $a, array $b): int => strcmp($this->str(data_get($b, 'kickoff')), $this->str(data_get($a, 'kickoff'))));

        return array_slice($finished, 0, $limit);
    }

    /**
     * A factual, number-dense intro line (the validated GEO lever: clear factual
     * statements with statistics). Derived from cached standings, never faked.
     *
     * @param  list<array{key: string, label: string, rows: list<array<string, mixed>>}>  $groups
     */
    private function competitionIntro(string $name, bool $isCup, array $groups): string
    {
        if ($groups === []) {
            return $isCup
                ? "Follow {$name} live on LiveGoal: groups, knockout bracket, fixtures, results and top scorers."
                : "Follow {$name} live on LiveGoal: full table, fixtures, results and top scorers.";
        }

        if ($isCup && count($groups) > 1) {
            $teams = array_sum(array_map(fn (array $g): int => count($g['rows']), $groups));
            $count = count($groups);

            return "{$name}: {$teams} teams across {$count} groups. Live standings, fixtures, results and top scorers on LiveGoal.";
        }

        $leader = $groups[0]['rows'][0] ?? null;

        if (is_array($leader) && $this->str(data_get($leader, 'team.name')) !== '') {
            $leaderName = $this->str(data_get($leader, 'team.name'));
            $points = $this->int(data_get($leader, 'points'));
            $played = $this->int(data_get($leader, 'played'));

            return "{$leaderName} lead the {$name} on {$points} points after {$played} matches. Live table, fixtures, results and top scorers on LiveGoal.";
        }

        return "Follow {$name} live on LiveGoal: full table, fixtures, results and top scorers.";
    }

    /**
     * A static hub page: indexable, distinct title/description, light breadcrumb.
     */
    private function hub(string $title, string $description, string $crumb): SeoMeta
    {
        $canonical = URL::current();

        return new SeoMeta(
            title: $this->brand($title),
            description: $description,
            canonical: $canonical,
            jsonLd: [
                $this->breadcrumb([
                    [Config::string('seo.site_name'), url('/')],
                    [$crumb, $canonical],
                ]),
            ],
        );
    }

    /**
     * A page whose entity could not be resolved from cache: generic copy, and
     * noindex,follow so a junk or cold deep link never enters the index.
     */
    private function cold(string $title, string $description, string $canonical): SeoMeta
    {
        return new SeoMeta(
            title: $this->brand($title),
            description: $description,
            canonical: $canonical,
            robots: 'noindex,follow',
        );
    }

    /**
     * Resolve a competition by code/id: try its cached detail, then fall back to
     * the warmed competitions list (so any of the ~13 free-tier competitions has
     * an indexable name even before its detail feed is warmed).
     *
     * @return array<string, mixed>|null
     */
    private function resolveCompetition(string $id): ?array
    {
        $raw = $this->football->peek("competition:{$id}");

        if ($raw !== null) {
            $c = $this->normalizer->competition($raw);

            if ($this->str(data_get($c, 'name')) !== '') {
                return $c;
            }
        }

        $list = $this->football->peek('competitions');

        if ($list !== null) {
            foreach ($this->normalizer->competitions($list) as $c) {
                if (strcasecmp($this->str(data_get($c, 'code')), $id) === 0 || $this->str(data_get($c, 'id')) === $id) {
                    return $c;
                }
            }
        }

        return null;
    }

    private function matchDescription(string $home, string $away, string $status, ?string $competition, ?string $venue, ?string $kickoff, int $homeScore, int $awayScore): string
    {
        $in = $competition !== null ? " in the {$competition}" : '';

        return match ($status) {
            'LIVE', 'HT' => sprintf('Live: %s %d–%d %s%s. Follow the score minute by minute on LiveGoal.', $home, $homeScore, $awayScore, $away, $in),
            'FT' => sprintf('Full time: %s %d–%d %s%s. Result, stats and standings on LiveGoal.', $home, $homeScore, $awayScore, $away, $in),
            default => sprintf(
                '%s vs %s%s%s. Live score, lineups and result on LiveGoal.',
                $home,
                $away,
                $this->kickoffPhrase($kickoff),
                $venue !== null ? " at {$venue}" : '',
            ),
        };
    }

    private function kickoffPhrase(?string $kickoff): string
    {
        if ($kickoff === null) {
            return '';
        }

        try {
            return ' kicks off '.Carbon::parse($kickoff)->format('D j M Y, H:i').' UTC';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sportsEvent(string $home, string $away, string $status, ?string $competition, ?string $venue, ?string $kickoff, string $canonical): array
    {
        $event = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => "{$home} vs {$away}",
            'sport' => 'Soccer',
            'url' => $canonical,
            'homeTeam' => ['@type' => 'SportsTeam', 'name' => $home],
            'awayTeam' => ['@type' => 'SportsTeam', 'name' => $away],
            'eventStatus' => $status === 'POSTPONED'
                ? 'https://schema.org/EventPostponed'
                : 'https://schema.org/EventScheduled',
        ];

        if ($kickoff !== null) {
            $event['startDate'] = $kickoff;
        }

        if ($venue !== null) {
            $event['location'] = ['@type' => 'Place', 'name' => $venue];
        }

        if ($competition !== null) {
            $event['superEvent'] = ['@type' => 'SportsOrganization', 'name' => $competition];
        }

        return $event;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items  [name, url] pairs.
     * @return array<string, mixed>
     */
    private function breadcrumb(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn (array $item, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item[0],
                    'item' => $item[1],
                ],
                $items,
                array_keys($items),
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function siteJsonLd(): array
    {
        $name = Config::string('seo.site_name');
        $home = url('/');

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $name,
                'url' => $home,
                'logo' => url(Config::string('seo.organization_logo')),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $name,
                'url' => $home,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => url('/search').'?q={query}',
                    ],
                    'query-input' => 'required name=query',
                ],
            ],
        ];
    }

    /**
     * Featured fixtures on a given Y-m-d (cache-only, crawler-safe).
     *
     * @return list<array<string, mixed>>
     */
    private function datedMatches(string $date): array
    {
        $agg = $this->featured->all(allowFetch: false);

        return $this->featured->onDate($agg['matches'], $date);
    }

    /** "2026-06-12" -> "12 June 2026" (falls back to the raw value on bad input). */
    private function humanDate(string $date): string
    {
        try {
            return Carbon::parse($date)->format('j F Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function brand(string $title): string
    {
        return $title.' | '.Config::string('seo.site_name');
    }

    private function str(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableStr(mixed $value): ?string
    {
        $string = $this->str($value);

        return $string === '' ? null : $string;
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
