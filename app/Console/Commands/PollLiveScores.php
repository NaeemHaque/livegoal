<?php

namespace App\Console\Commands;

use App\Services\Football\FeaturedMatches;
use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use App\Services\Push\MatchAlerts;
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
     * Cache key holding final snapshots (id => match) of recently finished
     * matches. The upstream "erases" matches at the final whistle just as it
     * does at half-time, so this is what lets the site show FT with the real
     * final score while the upstream record still claims the match never ran.
     */
    public const FINALS_KEY = 'live:finals';

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

    /**
     * Anchored minute beyond which a vanished, upstream-erased match is
     * presumed finished: regulation (90') plus very generous stoppage.
     * Knockout stages can run extra time, so their ceiling is far higher.
     */
    private const PRESUMED_FT_MINUTE = 105;

    private const PRESUMED_FT_MINUTE_KNOCKOUT = 140;

    /** Stages whose matches cannot go to extra time. */
    private const SINGLE_REGULATION_STAGES = ['GROUP_STAGE', 'REGULAR_SEASON', 'LEAGUE_STAGE'];

    /**
     * The interval lasts 15 minutes by law; once an HT marker is this old the
     * second half has restarted in reality, however much the feed lags.
     */
    private const INTERVAL_SECONDS = 900;

    private const PRESUME_SECOND_HALF_AFTER_SECONDS = 960;

    /**
     * The feed flips matches to IN_PLAY minutes after the real kickoff
     * (observed +6 to +15 across WC 2026 matchdays). Once the scheduled
     * kickoff is this far past, surface the match as live (0-0, presumed)
     * until the feed confirms; presumed entries are stateless — rebuilt from
     * the schedule every poll, never held, never recorded as events.
     */
    private const PRESUME_KICKOFF_AFTER_SECONDS = 60;

    /**
     * First feed signal within this window of the scheduled kickoff means the
     * match started on time and the feed was merely lagging — anchor the
     * clock at the schedule. Later than this, the start was genuinely
     * delayed (opening ceremonies): anchor at the signal.
     */
    private const KICKOFF_TRUST_SCHEDULE_SECONDS = 600;

    private const PRESUME_KICKOFF_WINDOW_SECONDS = 2100;

    protected $signature = 'app:poll-live-scores';

    protected $description = 'Poll in-play matches from football-data.org into cache for the whole site';

    public function __construct(
        private readonly MatchAlerts $alerts,
        private readonly FeaturedMatches $featured,
    ) {
        parent::__construct();
    }

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

        // The feed lags minutes behind the real second-half restart; once the
        // statutory interval has elapsed, an HT match is playing again.
        foreach ($matches as $i => $m) {
            $matches[$i] = $this->withPresumedSecondHalf($m);
        }

        // The feed also lags real kickoffs by minutes: surface scheduled
        // matches whose kickoff has passed as presumed-live until confirmed.
        $matches = $this->withPresumedKickoffs($matches);

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
        // Presumed entries carry no real data: no anchors, no goals, no pushes.
        if (($m['presumed'] ?? false) === true) {
            return;
        }

        $id = $this->str($m['id'] ?? null);
        $status = $this->str($m['status'] ?? null);

        if ($id === '' || ! in_array($status, ['LIVE', 'HT'], true)) {
            return;
        }

        $events = $this->recordedEvents($id);
        $before = count($events);

        if ($status === 'LIVE' && ! $this->hasEvent($events, 'KICKOFF')) {
            $events[] = [...$this->timelineEvent('KICKOFF', $m), 'at' => $this->kickoffAnchor($m)];
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
                $this->alerts->goalScored($m);
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

        $confirmed = is_array($existing) && is_array($existing['matches'] ?? null)
            ? array_filter($existing['matches'], fn ($m): bool => is_array($m) && ($m['presumed'] ?? false) !== true)
            : [];

        if ($confirmed === []) {
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

            if ($id === '' || isset($present[$id]) || ($m['presumed'] ?? false) === true) {
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
            if ($status === 'FINISHED' || $status === 'AWARDED') {
                $this->recordFinal($m, $detail);
            }

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
        // stays at half-time; anything deeper into the match stays LIVE —
        // until the anchored clock passes any plausible final whistle, at
        // which point the match is presumed finished (the upstream erases
        // matches at full time exactly as it does at half-time).
        if (in_array($status, ['TIMED', 'SCHEDULED'], true)) {
            $priorStatus = $m['status'] ?? null;

            if ($priorStatus === 'HT') {
                return $m;
            }

            $minute = $m['minute'] ?? null;

            if (is_int($minute) && $minute >= self::HT_WINDOW_MIN && $minute <= self::HT_WINDOW_MAX) {
                return $this->heldAtHalfTime($m, $id, $status);
            }

            $anchored = $this->liveMinute($m);

            if (is_int($anchored) && $anchored >= $this->presumedFullTimeMinute($m)) {
                Log::notice(sprintf(
                    'PollLiveScores: match %s vanished and erased upstream at anchored minute %d; presuming full-time.',
                    $id,
                    $anchored,
                ));
                $this->recordFinal($m, null);

                return null;
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
     * Flip a stale HT entry to LIVE once the statutory interval is over.
     *
     * The presumed restart (HT marker + 15 minutes) is recorded as the RESUME
     * anchor so the second-half clock starts from the right place; when the
     * feed later confirms the restart, the normal flow takes over seamlessly.
     *
     * @param  array<array-key, mixed>  $m
     * @return array<array-key, mixed>
     */
    private function withPresumedSecondHalf(array $m): array
    {
        if (($m['status'] ?? null) !== 'HT') {
            return $m;
        }

        $id = $this->str($m['id'] ?? null);
        $events = $this->recordedEvents($id);
        $halfTimeAt = $this->eventTime($events, 'HT');

        if ($id === '' || $halfTimeAt === null) {
            return $m;
        }

        $elapsed = Date::now()->getTimestamp() - Date::parse($halfTimeAt)->getTimestamp();

        if ($elapsed < self::PRESUME_SECOND_HALF_AFTER_SECONDS) {
            return $m;
        }

        if (! $this->hasEvent($events, 'RESUME')) {
            $events[] = [
                'type' => 'RESUME',
                'minute' => 46,
                'side' => null,
                'homeScore' => $this->nullableInt($m['homeScore'] ?? null),
                'awayScore' => $this->nullableInt($m['awayScore'] ?? null),
                'at' => Date::parse($halfTimeAt)->addSeconds(self::INTERVAL_SECONDS)->toIso8601String(),
            ];
            Cache::put(self::eventsKey($id), $events, self::EVENTS_TTL);

            Log::notice(sprintf(
                'PollLiveScores: match %s held at HT past the interval; presuming the second half restarted.',
                $id,
            ));
        }

        $live = [...$m, 'status' => 'LIVE'];
        $live['minute'] = $this->liveMinute($live) ?? 46;

        return $live;
    }

    /**
     * The anchored minute past which this match must be over.
     *
     * @param  array<array-key, mixed>  $m
     */
    private function presumedFullTimeMinute(array $m): int
    {
        $stage = $this->str($m['stage'] ?? null);

        return $stage === '' || in_array($stage, self::SINGLE_REGULATION_STAGES, true)
            ? self::PRESUMED_FT_MINUTE
            : self::PRESUMED_FT_MINUTE_KNOCKOUT;
    }

    /**
     * Store a finished match's final snapshot and FT timeline event, so the
     * site can show full-time with the real score while the upstream record
     * still misreports the match.
     *
     * @param  array<array-key, mixed>  $m  The last cached live entry.
     * @param  array<array-key, mixed>|null  $detail  The raw single-match record, when it reported FINISHED.
     */
    private function recordFinal(array $m, ?array $detail): void
    {
        $id = $this->str($m['id'] ?? null);

        if ($id === '') {
            return;
        }

        $final = [...$m, 'status' => 'FT', 'minute' => null];

        // A genuine FINISHED record carries the authoritative final score.
        $score = is_array($detail) ? ($detail['score'] ?? null) : null;
        $fullTime = is_array($score) ? ($score['fullTime'] ?? null) : null;

        if (is_array($fullTime) && is_int($fullTime['home'] ?? null) && is_int($fullTime['away'] ?? null)) {
            $final['homeScore'] = $fullTime['home'];
            $final['awayScore'] = $fullTime['away'];
        }

        $finals = Cache::get(self::FINALS_KEY);
        $finals = is_array($finals) ? $finals : [];
        $finals[$id] = $final;
        Cache::put(self::FINALS_KEY, $finals, self::EVENTS_TTL);

        $events = $this->recordedEvents($id);

        if (! $this->hasEvent($events, 'FT')) {
            $this->alerts->fullTime($final);
            $events[] = [
                'type' => 'FT',
                'minute' => null,
                'side' => null,
                'homeScore' => $this->nullableInt($final['homeScore'] ?? null),
                'awayScore' => $this->nullableInt($final['awayScore'] ?? null),
                'at' => Date::now()->toIso8601String(),
            ];
            Cache::put(self::eventsKey($id), $events, self::EVENTS_TTL);
        }
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
     * Surface scheduled matches whose kickoff has passed as presumed-live.
     *
     * Stateless: rebuilt from the (cache-only) featured schedule every poll
     * and replaced by the real feed entry the moment the upstream confirms,
     * which is also when the KICKOFF anchor and any pushes begin.
     *
     * @param  array<int, array<array-key, mixed>>  $matches
     * @return array<int, array<array-key, mixed>>
     */
    private function withPresumedKickoffs(array $matches): array
    {
        $present = [];

        foreach ($matches as $m) {
            $present[$this->str($m['id'] ?? null)] = true;
        }

        $now = Date::now()->getTimestamp();

        foreach ($this->featured->all(allowFetch: false)['matches'] as $m) {
            $id = $this->str($m['id'] ?? null);
            $kickoff = $m['kickoff'] ?? null;

            if (($m['status'] ?? null) !== 'SCHEDULED' || $id === '' || isset($present[$id]) || ! is_string($kickoff)) {
                continue;
            }

            $sinceKickoff = $now - Date::parse($kickoff)->getTimestamp();

            if ($sinceKickoff < self::PRESUME_KICKOFF_AFTER_SECONDS || $sinceKickoff > self::PRESUME_KICKOFF_WINDOW_SECONDS) {
                continue;
            }

            Log::notice(sprintf(
                'PollLiveScores: match %s kicked off %ds ago with no feed signal; presuming live.',
                $id,
                $sinceKickoff,
            ));

            $matches[] = [
                ...$m,
                'status' => 'LIVE',
                'homeScore' => 0,
                'awayScore' => 0,
                'minute' => max(1, min((int) floor($sinceKickoff / 60), 120)),
                'prevHomeScore' => null,
                'prevAwayScore' => null,
                'presumed' => true,
            ];
        }

        return $matches;
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
     * Where the match clock starts: the scheduled kickoff when the feed's
     * first live signal arrived within the trust window (on-time start,
     * lagging feed — observed +6/+7 minutes), otherwise the signal time.
     *
     * @param  array<array-key, mixed>  $m
     */
    private function kickoffAnchor(array $m): string
    {
        $scheduled = $m['kickoff'] ?? null;

        if (is_string($scheduled)) {
            $lag = Date::now()->getTimestamp() - Date::parse($scheduled)->getTimestamp();

            if ($lag >= 0 && $lag <= self::KICKOFF_TRUST_SCHEDULE_SECONDS) {
                return Date::parse($scheduled)->toIso8601String();
            }
        }

        return Date::now()->toIso8601String();
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
