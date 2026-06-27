<?php

namespace Tests\Feature\Push;

use App\Console\Commands\PollLiveScores;
use App\Models\PushSubscriber;
use App\Notifications\GoalScored;
use App\Services\Push\LiveGoalAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Covers ESPN-driven goal alerts (the real-time push path). The contract: a goal
 * alerts exactly once, deduped against the SAME live:events ledger the
 * football-data poller writes — so whichever source sees the goal first wins and
 * the other never repeats it.
 */
class LiveGoalAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('webpush.vapid.public_key', 'test-public');
        Config::set('webpush.vapid.private_key', 'test-private');

        Notification::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function match(): array
    {
        return [
            'id' => '537327',
            'status' => 'LIVE',
            'minute' => 30,
            'homeScore' => 0,
            'awayScore' => 0,
            'home' => ['id' => '769', 'name' => 'Mexico', 'tla' => 'MEX', 'crest' => null],
            'away' => ['id' => '774', 'name' => 'South Africa', 'tla' => 'RSA', 'crest' => null],
            'competition' => ['id' => '2000', 'code' => 'WC', 'name' => 'FIFA World Cup', 'short' => 'World Cup'],
            'kickoff' => '2026-06-11T19:00:00Z',
        ];
    }

    private function fan(): PushSubscriber
    {
        $subscriber = PushSubscriber::create();
        $subscriber->follows()->create(['type' => 'team', 'followed_id' => '769']);

        return $subscriber;
    }

    private function ledger(): string
    {
        return PollLiveScores::EVENTS_KEY_PREFIX.'537327';
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function recordedGoals(): array
    {
        $events = Cache::get($this->ledger());
        $events = is_array($events) ? $events : [];

        return array_values(array_filter($events, fn ($e): bool => is_array($e) && ($e['type'] ?? null) === 'GOAL'));
    }

    public function test_it_alerts_a_goal_from_live_scores_once(): void
    {
        $fan = $this->fan();

        app(LiveGoalAlerts::class)->fromLive($this->match(), ['homeScore' => 1, 'awayScore' => 0, 'minute' => 31]);

        Notification::assertSentToTimes($fan, GoalScored::class, 1);

        // Recorded in the shared ledger with the exact dedup key the poller reads.
        $goals = $this->recordedGoals();
        $this->assertCount(1, $goals);
        $this->assertSame('home', $goals[0]['side']);
        $this->assertSame(1, $goals[0]['homeScore']);
    }

    public function test_it_does_not_re_alert_across_harvests(): void
    {
        $fan = $this->fan();
        $live = ['homeScore' => 1, 'awayScore' => 0, 'minute' => 31];

        app(LiveGoalAlerts::class)->fromLive($this->match(), $live);
        app(LiveGoalAlerts::class)->fromLive($this->match(), $live);

        Notification::assertSentToTimes($fan, GoalScored::class, 1);
        $this->assertCount(1, $this->recordedGoals());
    }

    public function test_it_does_not_re_alert_a_goal_the_poller_already_recorded(): void
    {
        $fan = $this->fan();

        // The poller saw the goal first and wrote its GOAL event to the ledger.
        Cache::put($this->ledger(), [
            ['type' => 'GOAL', 'minute' => 28, 'side' => 'home', 'homeScore' => 1, 'awayScore' => 0, 'at' => '2026-06-11T19:28:00+00:00'],
        ], PollLiveScores::EVENTS_TTL);

        app(LiveGoalAlerts::class)->fromLive($this->match(), ['homeScore' => 1, 'awayScore' => 0, 'minute' => 31]);

        Notification::assertNotSentTo($fan, GoalScored::class);
    }

    public function test_a_multi_goal_jump_backfills_levels_but_alerts_once_per_side(): void
    {
        $fan = $this->fan();

        // ESPN jumped 0-0 -> 2-0 between harvests (a missed level).
        app(LiveGoalAlerts::class)->fromLive($this->match(), ['homeScore' => 2, 'awayScore' => 0, 'minute' => 40]);

        Notification::assertSentToTimes($fan, GoalScored::class, 1);

        // Both levels recorded so the poller never re-alerts the skipped 1-0.
        $homeScores = array_map(fn ($g) => $g['homeScore'], $this->recordedGoals());
        $this->assertSame([1, 2], $homeScores);
    }

    public function test_each_side_alerts_independently(): void
    {
        $fan = $this->fan();

        // Home 1-0 already on the ledger; ESPN now shows 1-1 — only the away goal is new.
        Cache::put($this->ledger(), [
            ['type' => 'GOAL', 'minute' => 20, 'side' => 'home', 'homeScore' => 1, 'awayScore' => 0, 'at' => '2026-06-11T19:20:00+00:00'],
        ], PollLiveScores::EVENTS_TTL);

        app(LiveGoalAlerts::class)->fromLive($this->match(), ['homeScore' => 1, 'awayScore' => 1, 'minute' => 55]);

        Notification::assertSentToTimes($fan, GoalScored::class, 1);
        $this->assertCount(2, $this->recordedGoals());
    }

    public function test_a_disallowed_goal_does_not_alert(): void
    {
        $fan = $this->fan();

        Cache::put($this->ledger(), [
            ['type' => 'GOAL', 'minute' => 20, 'side' => 'home', 'homeScore' => 1, 'awayScore' => 0, 'at' => '2026-06-11T19:20:00+00:00'],
        ], PollLiveScores::EVENTS_TTL);

        // VAR chalks it off: ESPN reverts to 0-0. Never alert, never roll back.
        app(LiveGoalAlerts::class)->fromLive($this->match(), ['homeScore' => 0, 'awayScore' => 0, 'minute' => 22]);

        Notification::assertNotSentTo($fan, GoalScored::class);
    }

    public function test_null_scores_are_ignored(): void
    {
        $fan = $this->fan();

        app(LiveGoalAlerts::class)->fromLive($this->match(), ['homeScore' => null, 'awayScore' => null, 'minute' => null]);

        Notification::assertNotSentTo($fan, GoalScored::class);
        $this->assertSame([], $this->recordedGoals());
    }
}
