<?php

namespace App\Services\Football;

use Illuminate\Support\Str;

/**
 * Resolves a football-data match to its ESPN counterpart and normalizes ESPN's
 * live data (real clock + official event minutes) into the app's shape.
 *
 * ESPN lists a match with its own event id and its own home/away orientation,
 * so resolution matches on the team set (TLA first, normalized name as a
 * fallback) and then re-orients every score and event to *our* home/away so
 * the rest of the app stays consistent.
 */
class EspnNormalizer
{
    /**
     * Find the ESPN scoreboard event for a football-data match.
     *
     * @param  array<array-key, mixed>|null  $scoreboard  ESPN scoreboard payload
     * @param  array{home: array{tla: ?string, name: ?string}, away: array{tla: ?string, name: ?string}}  $fdMatch
     * @return array{event: array<array-key, mixed>, homeTeamId: ?string, awayTeamId: ?string}|null
     */
    public function resolve(?array $scoreboard, array $fdMatch): ?array
    {
        $events = is_array($scoreboard['events'] ?? null) ? $scoreboard['events'] : [];

        $homeKey = $this->teamKey($fdMatch['home']['tla'] ?? null, $fdMatch['home']['name'] ?? null);
        $awayKey = $this->teamKey($fdMatch['away']['tla'] ?? null, $fdMatch['away']['name'] ?? null);

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $competition = $this->competition($event);
            $competitors = is_array($competition['competitors'] ?? null) ? $competition['competitors'] : [];

            if (count($competitors) !== 2) {
                continue;
            }

            $home = $this->matchCompetitor($competitors, $homeKey);
            $away = $this->matchCompetitor($competitors, $awayKey);

            // Both of our teams present (and not the same competitor) — this is it.
            if ($home !== null && $away !== null && $home !== $away) {
                return [
                    'event' => $event,
                    'homeTeamId' => $this->str(data_get($home, 'team.id')),
                    'awayTeamId' => $this->str(data_get($away, 'team.id')),
                ];
            }
        }

        return null;
    }

    /**
     * Normalize a resolved ESPN event (+ optional summary) into live data,
     * oriented to the football-data home/away.
     *
     * @param  array{event: array<array-key, mixed>, homeTeamId: ?string, awayTeamId: ?string}  $resolved
     * @param  array<array-key, mixed>|null  $summary  ESPN summary payload (keyEvents)
     * @return array{found: bool, espnId: ?string, status: string, minute: ?int, displayClock: ?string, homeScore: ?int, awayScore: ?int, events: list<array<array-key, mixed>>}
     */
    public function liveData(array $resolved, ?array $summary): array
    {
        $event = $resolved['event'];
        $competition = $this->competition($event);
        $status = $this->status($competition);

        return [
            'found' => true,
            'espnId' => $this->str($event['id'] ?? null),
            'status' => $status,
            'minute' => $this->parseMinute($this->displayClock($competition)),
            'displayClock' => $this->displayClock($competition),
            'homeScore' => $this->scoreFor($competition, $resolved['homeTeamId']),
            'awayScore' => $this->scoreFor($competition, $resolved['awayTeamId']),
            'events' => $this->events($summary, $resolved['homeTeamId'], $resolved['awayTeamId']),
        ];
    }

    /**
     * Empty live-data envelope for when no ESPN event resolves.
     *
     * @return array{found: bool, espnId: null, status: null, minute: null, displayClock: null, homeScore: null, awayScore: null, events: list<never>}
     */
    public function notFound(): array
    {
        return [
            'found' => false,
            'espnId' => null,
            'status' => null,
            'minute' => null,
            'displayClock' => null,
            'homeScore' => null,
            'awayScore' => null,
            'events' => [],
        ];
    }

    /**
     * The first competition node of an ESPN event.
     *
     * @param  array<array-key, mixed>  $event
     * @return array<array-key, mixed>
     */
    private function competition(array $event): array
    {
        $competitions = is_array($event['competitions'] ?? null) ? $event['competitions'] : [];
        $first = $competitions[0] ?? null;

        return is_array($first) ? $first : [];
    }

    /**
     * The competitor in this match whose team matches the given key, or null.
     *
     * @param  array<array-key, mixed>  $competitors
     * @return array<array-key, mixed>|null
     */
    private function matchCompetitor(array $competitors, string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        foreach ($competitors as $competitor) {
            if (! is_array($competitor)) {
                continue;
            }

            $abbr = $this->norm($this->str(data_get($competitor, 'team.abbreviation')));
            $name = $this->norm($this->str(data_get($competitor, 'team.displayName')));
            $short = $this->norm($this->str(data_get($competitor, 'team.shortDisplayName')));

            if ($key === $abbr || $key === $name || $key === $short) {
                return $competitor;
            }
        }

        return null;
    }

    /**
     * A normalized lookup key for a team — its TLA when present, else its name.
     */
    private function teamKey(?string $tla, ?string $name): string
    {
        $tlaKey = $this->norm($this->str($tla));

        return $tlaKey !== '' ? $tlaKey : $this->norm($this->str($name));
    }

    /**
     * Normalize a team token for comparison: ASCII-fold, lowercase, drop common
     * club affixes and any non-alphanumerics.
     */
    private function norm(string $value): string
    {
        $value = strtolower(Str::ascii($value));
        $value = (string) preg_replace('/\b(fc|afc|sc|cf|ac|club|cd|ssc)\b/', '', $value);

        return (string) preg_replace('/[^a-z0-9]/', '', $value);
    }

    /**
     * Map ESPN status to the app's status vocabulary.
     *
     * @param  array<array-key, mixed>  $competition
     */
    private function status(array $competition): string
    {
        $state = strtolower($this->str(data_get($competition, 'status.type.state')));

        // ESPN abbreviates `detail` ("HT") but spells the phase out in
        // `name`/`description` ("STATUS_HALFTIME", "Halftime") — match across all
        // three so an abbreviated half-time/penalties/extra-time isn't read LIVE.
        $phase = strtolower(
            $this->str(data_get($competition, 'status.type.name'))
            .' '.$this->str(data_get($competition, 'status.type.description'))
            .' '.$this->str(data_get($competition, 'status.type.detail'))
        );

        return match (true) {
            $state === 'pre' => 'SCHEDULED',
            $state === 'post' => 'FT',
            str_contains($phase, 'halftime') => 'HT',
            str_contains($phase, 'penalt'), str_contains($phase, 'shootout') => 'PEN',
            str_contains($phase, 'extra'), str_contains($phase, 'overtime') => 'ET',
            default => 'LIVE',
        };
    }

    /**
     * @param  array<array-key, mixed>  $competition
     */
    private function displayClock(array $competition): ?string
    {
        $clock = $this->str(data_get($competition, 'status.displayClock'));

        return $clock !== '' ? $clock : null;
    }

    /**
     * The integer match minute parsed from a display clock ("90'+6'" => 90).
     */
    private function parseMinute(?string $displayClock): ?int
    {
        if ($displayClock === null) {
            return null;
        }

        return preg_match('/(\d+)/', $displayClock, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * The score for the competitor with the given team id.
     *
     * @param  array<array-key, mixed>  $competition
     */
    private function scoreFor(array $competition, ?string $teamId): ?int
    {
        if ($teamId === null || $teamId === '') {
            return null;
        }

        $competitors = is_array($competition['competitors'] ?? null) ? $competition['competitors'] : [];

        foreach ($competitors as $competitor) {
            if (is_array($competitor) && $this->str(data_get($competitor, 'team.id')) === $teamId) {
                $score = data_get($competitor, 'score');

                return is_numeric($score) ? (int) $score : null;
            }
        }

        return null;
    }

    /**
     * Normalize the summary's keyEvents into the app's timeline shape, oriented
     * to our home/away and carrying the official event minute.
     *
     * @param  array<array-key, mixed>|null  $summary
     * @return list<array<array-key, mixed>>
     */
    private function events(?array $summary, ?string $homeTeamId, ?string $awayTeamId): array
    {
        $keyEvents = is_array($summary['keyEvents'] ?? null) ? $summary['keyEvents'] : [];
        $rows = [];

        foreach ($keyEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->mapEventType($this->str(data_get($event, 'type.text')));

            if ($type === null) {
                continue;
            }

            $teamId = $this->str(data_get($event, 'team.id'));
            $side = match ($teamId) {
                $homeTeamId => 'home',
                $awayTeamId => 'away',
                default => null,
            };

            $clock = $this->str(data_get($event, 'clock.displayValue')) ?: null;
            $participants = is_array($event['participants'] ?? null) ? $event['participants'] : [];

            $rows[] = [
                'sort' => $this->sortKey($clock),
                'type' => $type,
                'minute' => $this->parseMinute($clock),
                'clock' => $clock,
                'side' => $teamId === '' ? null : $side,
                'player' => $this->participant($participants, 0),
                'assist' => $type === 'GOAL' ? $this->participant($participants, 1) : null,
                'homeScore' => null,
                'awayScore' => null,
            ];
        }

        // Chronological order — ESPN's keyEvents arrive slightly out of order
        // (e.g. a 45' substitution after the 45'+5' half-time marker).
        usort($rows, fn (array $a, array $b): int => (int) $a['sort'] <=> (int) $b['sort']);

        // Stamp the running score on each goal so the timeline shows the
        // scoreline alongside the scorer (an own goal counts for the opponent).
        $home = 0;
        $away = 0;

        foreach ($rows as $i => $row) {
            if ($row['type'] === 'GOAL') {
                if ($row['side'] === 'home') {
                    $home++;
                } elseif ($row['side'] === 'away') {
                    $away++;
                }
            } elseif ($row['type'] === 'OWN_GOAL') {
                if ($row['side'] === 'home') {
                    $away++;
                } elseif ($row['side'] === 'away') {
                    $home++;
                }
            }

            if ($row['type'] === 'GOAL' || $row['type'] === 'OWN_GOAL') {
                $rows[$i]['homeScore'] = $home;
                $rows[$i]['awayScore'] = $away;
            }
        }

        return array_map(function (array $row): array {
            unset($row['sort']);

            return $row;
        }, $rows);
    }

    /**
     * A chronological sort key from a display clock: base minute, then any
     * stoppage. "45'+5'" => 4505, "67'" => 6700, kickoff (no clock) => 0.
     */
    private function sortKey(?string $clock): int
    {
        if ($clock === null || $clock === '') {
            return 0;
        }

        preg_match_all('/\d+/', $clock, $matches);
        $base = isset($matches[0][0]) ? (int) $matches[0][0] : 0;
        $extra = isset($matches[0][1]) ? (int) $matches[0][1] : 0;

        return $base * 100 + $extra;
    }

    /**
     * Map an ESPN event type label to the app's timeline event vocabulary, or
     * null to drop it (delays, period markers we don't surface).
     */
    private function mapEventType(string $text): ?string
    {
        $text = strtolower($text);

        return match (true) {
            str_contains($text, 'own goal') => 'OWN_GOAL',
            str_contains($text, 'goal') => 'GOAL',
            str_contains($text, 'penalty') && str_contains($text, 'miss') => null,
            str_contains($text, 'yellow') && str_contains($text, 'red') => 'RED_CARD',
            str_contains($text, 'red card') => 'RED_CARD',
            str_contains($text, 'yellow') => 'YELLOW_CARD',
            str_contains($text, 'substitution') => 'SUBSTITUTION',
            str_contains($text, 'kickoff') => 'KICKOFF',
            str_contains($text, 'halftime') => 'HT',
            str_contains($text, 'fulltime'), str_contains($text, 'final whistle') => 'FT',
            default => null,
        };
    }

    /**
     * The nth participant's athlete display name, or null.
     *
     * @param  array<array-key, mixed>  $participants
     */
    private function participant(array $participants, int $index): ?string
    {
        $name = $this->str(data_get($participants[$index] ?? null, 'athlete.displayName'));

        return $name !== '' ? $name : null;
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
