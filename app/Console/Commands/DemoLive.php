<?php

namespace App\Console\Commands;

use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use App\Services\Push\MatchAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

/**
 * Local-only helper to exercise the live UI (live cards, ticker, score-flip and
 * the goal toast) when nothing is actually in play. It writes real fixtures
 * flagged as LIVE into the same cache GET /api/live serves, so the polling
 * front-end picks them up; --goal bumps a score so the next poll fires the toast.
 *
 *   php artisan app:demo-live          # seed live matches (reload the browser)
 *   php artisan app:demo-live --goal   # score a goal (toast in visible tabs, push to hidden ones)
 *   php artisan app:demo-live --end    # full-time: clears the demo and pushes the final score
 *   php artisan app:demo-live --clear  # stop the demo
 */
class DemoLive extends Command
{
    protected $signature = 'app:demo-live {--goal} {--end} {--clear}';

    protected $description = 'Seed fake live matches (and fire goals) to test the live UI locally';

    public function __construct(private readonly MatchAlerts $alerts)
    {
        parent::__construct();
    }

    public function handle(FootballData $football, Normalizer $normalizer): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run app:demo-live in production.');

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            Cache::forget(PollLiveScores::CACHE_KEY);
            $this->info('Cleared demo live matches.');

            return self::SUCCESS;
        }

        $current = $this->liveMatches();

        if ($current !== [] && $this->option('goal')) {
            $this->scoreGoal($current);

            return self::SUCCESS;
        }

        if ($current !== [] && $this->option('end')) {
            $this->endMatches($current);

            return self::SUCCESS;
        }

        return $this->seed($football, $normalizer);
    }

    /**
     * The currently-cached live matches, if any.
     *
     * @return list<array<array-key, mixed>>
     */
    private function liveMatches(): array
    {
        $cached = Cache::get(PollLiveScores::CACHE_KEY);
        $matches = is_array($cached) ? ($cached['matches'] ?? null) : null;

        if (! is_array($matches)) {
            return [];
        }

        return array_values(array_filter($matches, is_array(...)));
    }

    /**
     * Bump one match's score so the next front-end poll detects a goal.
     *
     * @param  list<array<array-key, mixed>>  $matches
     */
    private function scoreGoal(array $matches): void
    {
        $i = array_rand($matches);
        $m = $matches[$i];
        $homeScored = random_int(0, 1) === 1;

        $home = $this->toInt($m['homeScore'] ?? 0);
        $away = $this->toInt($m['awayScore'] ?? 0);
        $m['prevHomeScore'] = $home;
        $m['prevAwayScore'] = $away;

        if ($homeScored) {
            $m['homeScore'] = ++$home;
        } else {
            $m['awayScore'] = ++$away;
        }

        $m['minute'] = min(90, $this->toInt($m['minute'] ?? 45) + random_int(1, 4));
        $matches[$i] = $m;

        $homeName = $this->teamName($m['home'] ?? null);
        $awayName = $this->teamName($m['away'] ?? null);

        $this->writeLive($matches);
        $this->alerts->goalScored($m);
        $this->info(sprintf(
            'GOAL! %s — %s %d–%d %s',
            $homeScored ? $homeName : $awayName,
            $homeName,
            $home,
            $away,
            $awayName,
        ));
    }

    /**
     * Full-time for every demo match: push the final score and clear the rail.
     *
     * @param  list<array<array-key, mixed>>  $matches
     */
    private function endMatches(array $matches): void
    {
        foreach ($matches as $m) {
            $this->alerts->fullTime([...$m, 'status' => 'FT', 'minute' => null]);
            $this->info(sprintf(
                'FT: %s %d–%d %s',
                $this->teamName($m['home'] ?? null),
                $this->toInt($m['homeScore'] ?? 0),
                $this->toInt($m['awayScore'] ?? 0),
                $this->teamName($m['away'] ?? null),
            ));
        }

        $this->writeLive([]);
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function teamName(mixed $team): string
    {
        if (is_array($team) && isset($team['name']) && is_string($team['name'])) {
            return $team['name'];
        }

        return '?';
    }

    /**
     * Seed a handful of real fixtures, flagged as LIVE with random scores.
     */
    private function seed(FootballData $football, Normalizer $normalizer): int
    {
        $result = $football->cached(
            'competition:WC:matches',
            Config::integer('football.ttl.matches'),
            '/competitions/WC/matches',
        );

        $all = is_array($result->data) ? $normalizer->matches($result->data) : [];
        $playable = array_values(array_filter($all, function (array $m): bool {
            $home = $m['home'] ?? null;
            $away = $m['away'] ?? null;

            return is_array($home) && ! empty($home['id'])
                && is_array($away) && ! empty($away['id']);
        }));

        if ($playable === []) {
            $this->error('No fixtures available to seed — the World Cup matches feed is empty.');

            return self::FAILURE;
        }

        $picked = array_slice($playable, 0, 4);

        foreach ($picked as $k => $m) {
            $m['status'] = 'LIVE';
            $m['minute'] = random_int(15, 75);
            $m['homeScore'] = random_int(0, 2);
            $m['awayScore'] = random_int(0, 2);
            $m['prevHomeScore'] = $m['homeScore'];
            $m['prevAwayScore'] = $m['awayScore'];
            $picked[$k] = $m;
        }

        $this->writeLive($picked);
        $this->info(sprintf(
            'Seeded %d live matches. Reload the browser, then run "--goal" to score.',
            count($picked),
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<array<array-key, mixed>>  $matches
     */
    private function writeLive(array $matches): void
    {
        Cache::put(PollLiveScores::CACHE_KEY, [
            'matches' => $matches,
            'count' => count($matches),
            'lastUpdated' => Date::now()->toIso8601String(),
        ], 3600);
    }
}
