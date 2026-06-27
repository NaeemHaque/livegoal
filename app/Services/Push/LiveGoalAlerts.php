<?php

namespace App\Services\Push;

use App\Console\Commands\PollLiveScores;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * Pushes goal alerts the moment ESPN (the real-time source) sees them, rather
 * than waiting for football-data's lagged free tier. Shares PollLiveScores'
 * timeline-events cache as the dedup ledger, so a goal alerts exactly once
 * whether ESPN here or the football-data poller saw it first — and the GOAL
 * events it records also surface on the match timeline.
 */
class LiveGoalAlerts
{
    public function __construct(private readonly MatchAlerts $alerts) {}

    /**
     * Alert on any goal ESPN's live score shows that the shared timeline hasn't
     * recorded yet, for one live match.
     *
     * @param  array<array-key, mixed>  $match  Normalized football-data match (names, competition, crest).
     * @param  array{status?: string, minute?: int|null, homeScore?: int|null, awayScore?: int|null}  $live  ESPN live fields.
     */
    public function fromLive(array $match, array $live): void
    {
        $id = $this->str($match['id'] ?? null);
        $home = $this->int($live['homeScore'] ?? null);
        $away = $this->int($live['awayScore'] ?? null);

        if ($id === '' || $home === null || $away === null) {
            return;
        }

        $key = PollLiveScores::EVENTS_KEY_PREFIX.$id;
        $events = $this->recordedEvents($key);
        $before = count($events);

        $payload = [
            ...$match,
            'homeScore' => $home,
            'awayScore' => $away,
            'minute' => $this->int($live['minute'] ?? null) ?? $this->int($match['minute'] ?? null),
        ];

        foreach (['home', 'away'] as $side) {
            $score = $side === 'home' ? $home : $away;
            $recorded = $this->goalsRecorded($events, $side);

            if ($score <= $recorded) {
                continue;
            }

            // ESPN can jump more than one goal between harvests: record every
            // missed level (so the poller never re-alerts a skipped one), but
            // send a single push for the side.
            for ($level = $recorded + 1; $level <= $score; $level++) {
                $events[] = [
                    'type' => 'GOAL',
                    'minute' => $payload['minute'],
                    'side' => $side,
                    'homeScore' => $side === 'home' ? $level : $home,
                    'awayScore' => $side === 'away' ? $level : $away,
                    'at' => Date::now()->toIso8601String(),
                ];
            }

            $this->alerts->goalScored($payload);
        }

        if (count($events) > $before) {
            Cache::put($key, $events, PollLiveScores::EVENTS_TTL);
        }
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function recordedEvents(string $key): array
    {
        $cached = Cache::get($key);

        return is_array($cached) ? array_values(array_filter($cached, is_array(...))) : [];
    }

    /**
     * The highest score a recorded GOAL for this side reached (0 if none) — the
     * same ledger PollLiveScores::hasGoalForSide reads, so neither source
     * double-alerts the same goal.
     *
     * @param  list<array<array-key, mixed>>  $events
     */
    private function goalsRecorded(array $events, string $side): int
    {
        $field = $side === 'home' ? 'homeScore' : 'awayScore';
        $max = 0;

        foreach ($events as $event) {
            if (($event['type'] ?? null) === 'GOAL' && ($event['side'] ?? null) === $side) {
                $score = $event[$field] ?? null;

                if (is_int($score) && $score > $max) {
                    $max = $score;
                }
            }
        }

        return $max;
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function int(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }
}
