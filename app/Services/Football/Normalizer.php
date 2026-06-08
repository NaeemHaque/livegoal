<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Config;

/**
 * Maps football-data.org v4 responses into SocPlay's normalized DTO arrays
 * (see docs/DATA_MODEL.md). Upstream values are `mixed`, so every field is read
 * through the typed coercion helpers below.
 */
class Normalizer
{
    /**
     * @param  array<array-key, mixed>  $payload  Upstream /competitions response.
     * @return list<array<string, mixed>>
     */
    public function competitions(array $payload): array
    {
        $items = $payload['competitions'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            fn (array $c): array => $this->competition($c),
            array_filter($items, is_array(...)),
        ));
    }

    /**
     * @param  array<array-key, mixed>  $c  A single upstream competition.
     * @return array<string, mixed>
     */
    public function competition(array $c): array
    {
        $code = $this->str($c['code'] ?? null);
        $meta = Config::array("football.meta.{$code}", []);
        $type = strtoupper($this->str($c['type'] ?? null));

        return [
            'id' => $this->str($c['id'] ?? null),
            'code' => $code,
            'name' => $this->str($c['name'] ?? null),
            'short' => $this->str($meta['short'] ?? null) ?: $this->str($c['name'] ?? null),
            'region' => $this->str(data_get($c, 'area.name')),
            'kind' => match ($type) {
                'CUP' => 'cup',
                'LEAGUE' => 'league',
                default => $this->str($meta['kind'] ?? null) ?: 'league',
            },
            'color' => $this->str($meta['color'] ?? null) ?: '#64748B',
            'featured' => ($meta['featured'] ?? false) === true,
            'emblem' => $this->nullableStr(data_get($c, 'emblem')),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $t  A single upstream team.
     * @return array<string, mixed>
     */
    public function team(array $t): array
    {
        $name = $this->str($t['name'] ?? null);
        $tla = $this->str($t['tla'] ?? null);

        return [
            'id' => $this->str($t['id'] ?? null),
            'name' => $name,
            'short' => $tla ?: ($this->str($t['shortName'] ?? null) ?: $name),
            'tla' => $tla ?: null,
            'crest' => $this->nullableStr($t['crest'] ?? null),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $payload  Upstream /matches response.
     * @return list<array<string, mixed>>
     */
    public function matches(array $payload): array
    {
        $items = $payload['matches'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            fn (array $m): array => $this->match($m),
            array_filter($items, is_array(...)),
        ));
    }

    /**
     * @param  array<array-key, mixed>  $m  A single upstream match.
     * @return array<string, mixed>
     */
    public function match(array $m): array
    {
        $competition = $m['competition'] ?? null;
        $home = $m['homeTeam'] ?? null;
        $away = $m['awayTeam'] ?? null;

        return [
            'id' => $this->str($m['id'] ?? null),
            'competition' => is_array($competition) ? $this->competition($competition) : null,
            'stage' => $this->str($m['stage'] ?? null),
            'group' => $this->nullableStr($m['group'] ?? null),
            'status' => $this->mapStatus(strtoupper($this->str($m['status'] ?? null))),
            'minute' => $this->nullableInt(data_get($m, 'minute')),
            'home' => is_array($home) ? $this->team($home) : null,
            'away' => is_array($away) ? $this->team($away) : null,
            'homeScore' => $this->nullableInt(data_get($m, 'score.fullTime.home')),
            'awayScore' => $this->nullableInt(data_get($m, 'score.fullTime.away')),
            'winner' => $this->nullableStr(data_get($m, 'score.winner')),
            'kickoff' => $this->nullableStr($m['utcDate'] ?? null),
            'venue' => $this->nullableStr($m['venue'] ?? null),
            'referee' => $this->nullableStr(data_get($m, 'referees.0.name')),
        ];
    }

    /**
     * Map an upstream match status to SocPlay's set (see docs/DATA_MODEL.md).
     */
    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'TIMED', 'SCHEDULED' => 'SCHEDULED',
            'IN_PLAY', 'SUSPENDED' => 'LIVE',
            'PAUSED' => 'HT',
            'FINISHED' => 'FT',
            'POSTPONED', 'CANCELLED' => 'POSTPONED',
            default => $status !== '' ? $status : 'SCHEDULED',
        };
    }

    /**
     * Parse standings into groups. Tournaments return one standing per group
     * (World Cup A–L); leagues return a single overall table. Only the overall
     * ("TOTAL") standing per group is used (HOME/AWAY splits are ignored).
     *
     * @param  array<array-key, mixed>  $payload  Upstream /standings response.
     * @return array{groups: list<array{key: string, label: string, rows: list<array<string, mixed>>}>}
     */
    public function standings(array $payload): array
    {
        $standings = $payload['standings'] ?? [];

        if (! is_array($standings)) {
            return ['groups' => []];
        }

        $groups = [];

        foreach ($standings as $standing) {
            if (! is_array($standing) || strtoupper($this->str($standing['type'] ?? null)) !== 'TOTAL') {
                continue;
            }

            $table = $standing['table'] ?? [];

            if (! is_array($table)) {
                continue;
            }

            $group = $this->str($standing['group'] ?? null);
            $isGroup = $group !== '';
            $total = count($table);

            $groups[] = [
                'key' => $group ?: 'TOTAL',
                'label' => $isGroup ? $this->humanizeGroup($group) : 'Table',
                'rows' => array_values(array_map(
                    fn (array $row): array => $this->standingRow($row, $isGroup, $total),
                    array_filter($table, is_array(...)),
                )),
            ];
        }

        return ['groups' => $groups];
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @return array<string, mixed>
     */
    protected function standingRow(array $row, bool $isGroup, int $total): array
    {
        $position = $this->int($row['position'] ?? null);
        $team = $row['team'] ?? null;

        return [
            'position' => $position,
            'team' => is_array($team) ? $this->team($team) : null,
            'played' => $this->int($row['playedGames'] ?? null),
            'won' => $this->int($row['won'] ?? null),
            'draw' => $this->int($row['draw'] ?? null),
            'lost' => $this->int($row['lost'] ?? null),
            'goalsFor' => $this->int($row['goalsFor'] ?? null),
            'goalsAgainst' => $this->int($row['goalsAgainst'] ?? null),
            'goalDifference' => $this->int($row['goalDifference'] ?? null),
            'points' => $this->int($row['points'] ?? null),
            'form' => $this->parseForm($row['form'] ?? null),
            'zone' => $this->zone($position, $isGroup, $total),
        ];
    }

    /**
     * Recent results as W/D/L pills (last 5).
     *
     * @return list<string>
     */
    protected function parseForm(mixed $form): array
    {
        if (! is_string($form)) {
            return [];
        }

        preg_match_all('/[WDL]/', strtoupper($form), $matches);

        return array_slice($matches[0], -5);
    }

    /** Qualification-zone hint for row coloring (derived from table structure). */
    protected function zone(int $position, bool $isGroup, int $total): ?string
    {
        if ($position < 1) {
            return null;
        }

        if ($isGroup) {
            return $position <= 2 ? 'qualify' : null;
        }

        if ($position <= 4) {
            return 'qualify';
        }

        if ($total >= 6 && $position > $total - 3) {
            return 'relegation';
        }

        return null;
    }

    /** "GROUP_A" -> "Group A". */
    protected function humanizeGroup(string $group): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $group)));
    }

    /** Coerce a mixed value to a string ('' when not scalar). */
    protected function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** Coerce a mixed value to a string, or null when absent/non-scalar. */
    protected function nullableStr(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /** Coerce a mixed value to an int (0 when not numeric). */
    protected function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** Coerce a mixed value to an int, or null when not numeric. */
    protected function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
