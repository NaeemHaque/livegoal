<?php

namespace App\Console\Commands;

use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * The single site-wide live poller. Fetches all in-play/paused matches in one
 * upstream request and writes them to cache so every visitor reads from cache
 * (see docs/LIVE_POLLING.md). Runs every minute via the scheduler.
 */
class PollLiveScores extends Command
{
    /** Cache key holding the live payload that GET /api/live serves. */
    public const CACHE_KEY = 'live:matches';

    /** Cache key counting consecutive empty polls while matches were live. */
    public const EMPTY_STREAK_KEY = 'live:empty-streak';

    /** Cache key prefix for the self-built per-match timeline events. */
    public const EVENTS_KEY_PREFIX = 'live:events:';

    /**
     * TTL for per-match timeline events: ~26 hours, long enough to outlive
     * the match day (delays, extra time) without piling up stale timelines.
     */
    public const EVENTS_TTL = 93600;

    /**
     * Consecutive empty polls required before clearing a non-empty live cache.
     * football-data.org's free tier flaps between "in play" and "no matches"
     * across requests (observed during WC 2026 kickoff); a single empty answer
     * while matches are live is more likely upstream noise than full time.
     */
    public const EMPTY_CONFIRMATIONS = 3;

    /**
     * Single-match statuses confirming a vanished match is genuinely over
     * (or shelved), so it may be dropped from the live payload.
     */
    private const TERMINAL_STATUSES = ['FINISHED', 'AWARDED', 'POSTPONED', 'SUSPENDED', 'CANCELLED'];

    /**
     * Minute window in which a vanished LIVE match (whose own record lies
     * TIMED) is plausibly at the interval. Later than this, the match was
     * already deep into the second half — it stays LIVE, never back to HT.
     */
    private const HT_WINDOW_MIN = 40;

    private const HT_WINDOW_MAX = 70;

    protected $signature = 'app:poll-live-scores';

    protected $description = 'Poll in-play matches from football-data.org into cache for the whole site';

    public function handle(FootballData $football, Normalizer $normalizer): int
    {
        $raw = $football->get('/matches', ['status' => 'IN_PLAY,PAUSED']);

        // Upstream unavailable: keep the last good cache and refresh its TTL so it
        // survives consecutive failures rather than expiring mid-outage.
        if ($raw === null) {
            $existing = Cache::get(self::CACHE_KEY);

            if ($existing !== null) {
                Cache::put(self::CACHE_KEY, $existing, Config::integer('football.ttl.live'));
            }

            Log::warning('PollLiveScores: upstream unavailable, keeping last live cache');
            $this->warn('Upstream unavailable; kept last live cache.');

            return self::SUCCESS;
        }

        $matches = $normalizer->matches($raw);

        // Upstream flap guard: a sudden "no live matches" while matches were
        // live is held back until confirmed by consecutive empty polls.
        if ($matches === [] && $this->holdUnconfirmedEmpty()) {
            return self::SUCCESS;
        }

        Cache::forget(self::EMPTY_STREAK_KEY);

        $prior = $this->priorScores();
        $changed = [];

        foreach ($matches as $i => $m) {
            $id = $this->str($m['id'] ?? null);
            $previous = $prior[$id] ?? null;

            // Upstream nodes disagree mid-match and can serve yesterday's
            // score; never let a score go backwards without confirmation.
            if ($previous !== null) {
                [$matches[$i]['homeScore'], $matches[$i]['awayScore']] = $this->guardedScores($id, $m, $previous);
                $m = $matches[$i];
            }

            $matches[$i]['minute'] = $this->liveMinute($m);
            $matches[$i]['prevHomeScore'] = $previous['home'] ?? null;
            $matches[$i]['prevAwayScore'] = $previous['away'] ?? null;

            if ($previous !== null && ($m['homeScore'] !== $previous['home'] || $m['awayScore'] !== $previous['away'])) {
                $changed[] = $matches[$i];
            }
        }

        // The free tier "erases" matches during half-time (bulk feed drops
        // them, their own record reverts to TIMED). Verify every vanished
        // match against its single-match status before letting it disappear.
        $matches = $this->withVerifiedHolds($matches, $football, $normalizer);

        // Self-built timelines: the free tier has no event feed, so derive
        // kickoff / goal / half-time events from the polls themselves —
        // after the holds, so a held half-time still records its HT event.
        foreach ($matches as $m) {
            $this->recordTimelineEvents($m);
        }

        Cache::put(self::CACHE_KEY, [
            'matches' => $matches,
            'count' => count($matches),
            'lastUpdated' => Date::now()->toIso8601String(),
        ], Config::integer('football.ttl.live'));

        // ---- Future broadcast extension point (NOT wired in v1) ----
        // When Reverb is added on a VPS, dispatch a real-time event per changed
        // score here; nothing else in the polling flow needs to change:
        //
        //     foreach ($changed as $m) {
        //         broadcast(new \App\Events\ScoreUpdated($m));
        //     }

        $this->info(sprintf('Live: %d match(es), %d score change(s).', count($matches), count($changed)));

        return self::SUCCESS;
    }

    /** Cache key holding the self-built event timeline for one match. */
    public static function eventsKey(string $matchId): string
    {
        return self::EVENTS_KEY_PREFIX.$matchId;
    }

    /**
     * Append self-built timeline events for one polled match: KICKOFF the
     * first time it is seen LIVE, one GOAL per score increase (side derived
     * from the prev-score diff), and HT the first time it is seen paused.
     *
     * @param  array<array-key, mixed>  $m  A normalized or cache-held live match (minute + prev scores already set).
     */
    private function recordTimelineEvents(array $m): void
    {
        $id = $this->str($m['id'] ?? null);
        $status = $this->str($m['status'] ?? null);

        if ($id === '' || ! in_array($status, ['LIVE', 'HT'], true)) {
            return;
        }

        $events = $this->recordedEvents($id);
        $before = count($events);

        if ($status === 'LIVE' && ! $this->hasEvent($events, 'KICKOFF')) {
            $events[] = $this->timelineEvent('KICKOFF', $m);
        }

        // Back LIVE after a recorded half-time: the second half restarted.
        // The timestamp anchors the second-half clock (see liveMinute()).
        if ($status === 'LIVE' && $this->hasEvent($events, 'HT') && ! $this->hasEvent($events, 'RESUME')) {
            $events[] = $this->timelineEvent('RESUME', $m);
        }

        $scores = [
            'home' => [$m['homeScore'] ?? null, $m['prevHomeScore'] ?? null],
            'away' => [$m['awayScore'] ?? null, $m['prevAwayScore'] ?? null],
        ];

        foreach ($scores as $side => [$current, $previous]) {
            $current = $this->nullableInt($current);
            $previous = $this->nullableInt($previous);

            if ($current !== null && $previous !== null && $current > $previous
                && ! $this->hasGoalForSide($events, $side, $current)) {
                $events[] = $this->timelineEvent('GOAL', $m, $side);
            }
        }

        if ($status === 'HT' && ! $this->hasEvent($events, 'HT')) {
            $events[] = $this->timelineEvent('HT', $m);
        }

        if (count($events) > $before) {
            Cache::put(self::eventsKey($id), $events, self::EVENTS_TTL);
        }
    }

    /**
     * Previously recorded timeline events for a match, oldest first.
     *
     * @return list<array<array-key, mixed>>
     */
    private function recordedEvents(string $id): array
    {
        $cached = Cache::get(self::eventsKey($id));

        if (! is_array($cached)) {
            return [];
        }

        return array_values(array_filter($cached, is_array(...)));
    }

    /**
     * Whether this side's goal taking it to this score was already recorded —
     * score oscillation across stale upstream nodes must not produce
     * duplicate goal events for the same goal.
     *
     * @param  list<array<array-key, mixed>>  $events
     */
    private function hasGoalForSide(array $events, string $side, int $score): bool
    {
        $key = $side === 'home' ? 'homeScore' : 'awayScore';

        foreach ($events as $event) {
            if (($event['type'] ?? null) === 'GOAL'
                && ($event['side'] ?? null) === $side
                && ($event[$key] ?? null) === $score) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<array-key, mixed>>  $events
     */
    private function hasEvent(array $events, string $type): bool
    {
        foreach ($events as $event) {
            if (($event['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $m
     * @return array{type: string, minute: int|null, side: string|null, homeScore: int|null, awayScore: int|null, at: string}
     */
    private function timelineEvent(string $type, array $m, ?string $side = null): array
    {
        return [
            'type' => $type,
            'minute' => $this->nullableInt($m['minute'] ?? null),
            'side' => $side,
            'homeScore' => $this->nullableInt($m['homeScore'] ?? null),
            'awayScore' => $this->nullableInt($m['awayScore'] ?? null),
            'at' => Date::now()->toIso8601String(),
        ];
    }

    /**
     * Map of match id => prior score, from the previous poll's cache.
     *
     * @return array<string, array{home: mixed, away: mixed}>
     */
    private function priorScores(): array
    {
        $previous = Cache::get(self::CACHE_KEY);
        $scores = [];

        if (is_array($previous) && is_array($previous['matches'] ?? null)) {
            foreach ($previous['matches'] as $m) {
                if (is_array($m) && isset($m['id'])) {
                    $scores[$this->str($m['id'])] = [
                        'home' => $m['homeScore'] ?? null,
                        'away' => $m['awayScore'] ?? null,
                    ];
                }
            }
        }

        return $scores;
    }

    /**
     * Whether an empty upstream answer should be held back as a likely flap.
     *
     * Keeps the existing live cache (TTL refreshed) until the empty result has
     * been confirmed EMPTY_CONFIRMATIONS polls in a row; only holds while the
     * current cache actually has matches, so a quiet site clears instantly.
     */
    private function holdUnconfirmedEmpty(): bool
    {
        $existing = Cache::get(self::CACHE_KEY);

        $hasLiveMatches = is_array($existing)
            && is_array($existing['matches'] ?? null)
            && $existing['matches'] !== [];

        if (! $hasLiveMatches) {
            return false;
        }

        $previousStreak = Cache::get(self::EMPTY_STREAK_KEY, 0);
        $streak = (is_int($previousStreak) ? $previousStreak : 0) + 1;

        if ($streak >= self::EMPTY_CONFIRMATIONS) {
            Cache::forget(self::EMPTY_STREAK_KEY);

            return false;
        }

        $ttl = Config::integer('football.ttl.live');

        Cache::put(self::EMPTY_STREAK_KEY, $streak, $ttl * self::EMPTY_CONFIRMATIONS);
        Cache::put(self::CACHE_KEY, $existing, $ttl);

        Log::notice(sprintf(
            'PollLiveScores: empty upstream answer while matches are live; holding cache (%d/%d).',
            $streak,
            self::EMPTY_CONFIRMATIONS,
        ));
        $this->warn(sprintf('Empty answer while live; held last cache (%d/%d).', $streak, self::EMPTY_CONFIRMATIONS));

        return true;
    }

    /**
     * Re-add vanished matches whose own record says they are not over.
     *
     * A match present in the previous poll but missing from the new bulk
     * answer is verified against GET /matches/{id}: a terminal status drops
     * it; IN_PLAY keeps it live (bulk-feed flap); PAUSED — and TIMED/SCHEDULED
     * mid-match, the free tier's half-time lie — keep it with its last real
     * score as HT; a failed lookup keeps the last good state untouched.
     *
     * @param  array<int, array<array-key, mixed>>  $matches
     * @return array<int, array<array-key, mixed>>
     */
    private function withVerifiedHolds(array $matches, FootballData $football, Normalizer $normalizer): array
    {
        $present = [];

        foreach ($matches as $m) {
            $present[$this->str($m['id'] ?? null)] = true;
        }

        $previous = Cache::get(self::CACHE_KEY);
        $priorMatches = is_array($previous) && is_array($previous['matches'] ?? null)
            ? $previous['matches']
            : [];

        foreach ($priorMatches as $m) {
            if (! is_array($m)) {
                continue;
            }

            $id = $this->str($m['id'] ?? null);

            if ($id === '' || isset($present[$id])) {
                continue;
            }

            $held = $this->verifyVanishedMatch($m, $id, $football, $normalizer);

            if ($held !== null) {
                $matches[] = $held;
            }
        }

        return $matches;
    }

    /**
     * The verified hold for one vanished match, or null to drop it.
     *
     * @param  array<array-key, mixed>  $m  A match as read back from the cache.
     * @return array<array-key, mixed>|null
     */
    private function verifyVanishedMatch(array $m, string $id, FootballData $football, Normalizer $normalizer): ?array
    {
        // The poller runs sub-minute; cache each match's verification lookup
        // briefly so vanished matches cost at most ~1 upstream call a minute.
        $verifyKey = 'live:verify:'.$id;
        $detail = Cache::get($verifyKey);

        if (! is_array($detail)) {
            $detail = $football->get('/matches/'.$id);

            if (is_array($detail)) {
                Cache::put($verifyKey, $detail, 55);
            }
        }

        $status = is_array($detail) ? $this->str($detail['status'] ?? null) : '';

        if (in_array($status, self::TERMINAL_STATUSES, true)) {
            return null;
        }

        // The single record carries the freshest truth when it is in play or
        // paused — use it (scores included), never the stale cached entry.
        $fresh = null;

        if (is_array($detail) && in_array($status, ['IN_PLAY', 'PAUSED'], true)) {
            $fresh = $normalizer->matches(['matches' => [$detail]])[0] ?? null;
        }

        // Still in play per its own record: the bulk feed flapped — carry the
        // fresh score over, with the clock ticking on.
        if ($status === 'IN_PLAY') {
            return $fresh !== null ? $this->refreshedFromDetail($fresh, $m) : $this->keptLive($m);
        }

        // PAUSED is the documented interval status: genuinely half-time.
        if ($status === 'PAUSED') {
            return $fresh !== null ? $this->refreshedFromDetail($fresh, $m) : $this->heldAtHalfTime($m, $id, $status);
        }

        // TIMED/SCHEDULED mid-match is the free tier erasing the match. The
        // record carries no truth, so fall back to what we last knew:
        // an interval-window LIVE match is presumed at half-time; an HT hold
        // stays at half-time; anything deeper into the match stays LIVE.
        if (in_array($status, ['TIMED', 'SCHEDULED'], true)) {
            $priorStatus = $m['status'] ?? null;

            if ($priorStatus === 'HT') {
                return $m;
            }

            $minute = $m['minute'] ?? null;

            if (is_int($minute) && $minute >= self::HT_WINDOW_MIN && $minute <= self::HT_WINDOW_MAX) {
                return $this->heldAtHalfTime($m, $id, $status);
            }

            return $this->keptLive($m);
        }

        // Lookup failed or unrecognized status: keep the last good state.
        return $m;
    }

    /**
     * The score pair to publish for a match, holding back unconfirmed drops.
     *
     * A lower score than the previous poll usually means a stale upstream
     * node, not a disallowed goal; the drop is published only after it has
     * been answered consistently for EMPTY_CONFIRMATIONS polls.
     *
     * @param  array<array-key, mixed>  $m
     * @param  array{home: mixed, away: mixed}  $previous
     * @return array{0: mixed, 1: mixed}
     */
    private function guardedScores(string $id, array $m, array $previous): array
    {
        $home = $m['homeScore'] ?? null;
        $away = $m['awayScore'] ?? null;
        $prevHome = $previous['home'] ?? null;
        $prevAway = $previous['away'] ?? null;
        $streakKey = 'live:score-drop:'.$id;

        $dropped = is_int($home) && is_int($away) && is_int($prevHome) && is_int($prevAway)
            && ($home < $prevHome || $away < $prevAway);

        if (! $dropped) {
            Cache::forget($streakKey);

            return [$home, $away];
        }

        $previousStreak = Cache::get($streakKey, 0);
        $streak = (is_int($previousStreak) ? $previousStreak : 0) + 1;

        if ($streak >= self::EMPTY_CONFIRMATIONS) {
            Cache::forget($streakKey);

            return [$home, $away];
        }

        Cache::put($streakKey, $streak, Config::integer('football.ttl.live') * self::EMPTY_CONFIRMATIONS);

        Log::notice(sprintf(
            'PollLiveScores: match %s score dropped %s-%s -> %s-%s; holding previous score (%d/%d).',
            $id,
            $this->str($prevHome),
            $this->str($prevAway),
            $this->str($home),
            $this->str($away),
            $streak,
            self::EMPTY_CONFIRMATIONS,
        ));

        return [$prevHome, $prevAway];
    }

    /**
     * Keep a vanished match live, with its approximate minute ticking on.
     *
     * @param  array<array-key, mixed>  $m
     * @return array<array-key, mixed>
     */
    private function keptLive(array $m): array
    {
        return [...$m, 'minute' => $this->liveMinute($m) ?? ($m['minute'] ?? null)];
    }

    /**
     * A freshly normalized single-match record, enriched like a bulk entry:
     * derived minute, and prev scores taken from the cached entry so goal
     * detection still fires across the gap.
     *
     * @param  array<string, mixed>  $fresh
     * @param  array<array-key, mixed>  $cached
     * @return array<string, mixed>
     */
    private function refreshedFromDetail(array $fresh, array $cached): array
    {
        $fresh['minute'] = $this->liveMinute($fresh);
        $fresh['prevHomeScore'] = $cached['homeScore'] ?? null;
        $fresh['prevAwayScore'] = $cached['awayScore'] ?? null;

        return $fresh;
    }

    /**
     * The half-time hold for a vanished match.
     *
     * @param  array<array-key, mixed>  $m
     * @return array<array-key, mixed>
     */
    private function heldAtHalfTime(array $m, string $id, string $status): array
    {
        Log::notice(sprintf(
            'PollLiveScores: match %s vanished from the live feed (own status: %s); holding as half-time.',
            $id,
            $status,
        ));

        return [
            ...$m,
            'status' => 'HT',
            'minute' => 45,
            'prevHomeScore' => $m['homeScore'] ?? null,
            'prevAwayScore' => $m['awayScore'] ?? null,
        ];
    }

    /**
     * Approximate live minute (free tier has no real minute).
     *
     * Anchored to transitions this poller observed itself — the recorded
     * KICKOFF (first seen live) and RESUME (live again after half-time)
     * timestamps — which track the real clock far better than the scheduled
     * kickoff when a match starts late or after the 15-minute interval.
     * Falls back to the scheduled kickoff when no anchors exist yet.
     *
     * @param  array<array-key, mixed>  $m
     */
    private function liveMinute(array $m): ?int
    {
        $status = $m['status'] ?? null;

        if ($status === 'HT') {
            return 45;
        }

        if ($status !== 'LIVE') {
            return null;
        }

        $events = $this->recordedEvents($this->str($m['id'] ?? null));

        $resumedAt = $this->eventTime($events, 'RESUME');

        if ($resumedAt !== null) {
            return min(45 + max(1, $this->minutesSince($resumedAt)), 120);
        }

        $kickedOffAt = $this->eventTime($events, 'KICKOFF');

        if ($kickedOffAt !== null) {
            return min(max(1, $this->minutesSince($kickedOffAt)), 120);
        }

        $kickoff = $m['kickoff'] ?? null;

        if (! is_string($kickoff)) {
            return null;
        }

        return max(1, min($this->minutesSince($kickoff), 120));
    }

    /**
     * Timestamp of the first recorded event of a type, or null.
     *
     * @param  list<array<array-key, mixed>>  $events
     */
    private function eventTime(array $events, string $type): ?string
    {
        foreach ($events as $event) {
            if (($event['type'] ?? null) === $type && is_string($event['at'] ?? null)) {
                return $event['at'];
            }
        }

        return null;
    }

    private function minutesSince(string $iso): int
    {
        return (int) floor((Date::now()->getTimestamp() - Date::parse($iso)->getTimestamp()) / 60);
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
