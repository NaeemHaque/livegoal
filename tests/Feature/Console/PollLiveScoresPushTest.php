<?php

namespace Tests\Feature\Console;

use App\Models\PushSubscriber;
use App\Notifications\GoalScored;
use App\Notifications\MatchFullTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Covers the poller → push pipeline (docs/PUSH_NOTIFICATIONS.md): pushes fire
 * exactly where GOAL/FT timeline events are appended, so the poller's flap
 * and dedupe guards bound the sends — one real goal or final whistle, one
 * notification, however noisy the upstream.
 */
class PollLiveScoresPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('football.token', 'test-token');
        Config::set('football.base_url', 'https://api.football-data.org/v4');
        Config::set('webpush.vapid.public_key', 'test-public');
        Config::set('webpush.vapid.private_key', 'test-private');

        Http::preventStrayRequests();
        Sleep::fake();
        Notification::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function upstreamMatch(int $id, string $status, ?int $home, ?int $away): array
    {
        return [
            'id' => $id,
            'utcDate' => '2026-06-11T19:00:00Z',
            'status' => $status,
            'stage' => 'GROUP_STAGE',
            'group' => 'GROUP_A',
            'competition' => [
                'id' => 2000,
                'name' => 'FIFA World Cup',
                'code' => 'WC',
                'type' => 'CUP',
            ],
            'homeTeam' => ['id' => 769, 'name' => 'Mexico', 'tla' => 'MEX', 'crest' => null],
            'awayTeam' => ['id' => 774, 'name' => 'South Africa', 'tla' => 'RSA', 'crest' => null],
            'score' => ['winner' => null, 'fullTime' => ['home' => $home, 'away' => $away]],
            'venue' => null,
            'referees' => [],
        ];
    }

    private function fanOf(string $type, string $id): PushSubscriber
    {
        $subscriber = PushSubscriber::create();
        $subscriber->follows()->create(['type' => $type, 'followed_id' => $id]);

        return $subscriber;
    }

    public function test_a_goal_pushes_to_followers_once(): void
    {
        $fan = $this->fanOf('team', '769');
        $bystander = $this->fanOf('team', '999');

        Http::fake([
            '*/matches*' => Http::sequence()
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', 0, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', 1, 0)]], 200)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();
        $this->artisan('app:poll-live-scores')->assertSuccessful();

        Notification::assertSentToTimes($fan, GoalScored::class, 1);
        Notification::assertNotSentTo($bystander, GoalScored::class);
    }

    public function test_score_flaps_do_not_repeat_the_goal_push(): void
    {
        $fan = $this->fanOf('team', '769');

        // 1-0 seen, stale node answers 0-0 (held by the drop guard), 1-0 again:
        // the goal event dedupe means exactly one push ever goes out.
        Http::fake([
            '*/matches*' => Http::sequence()
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', 0, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', 1, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', 0, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', 1, 0)]], 200)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        foreach (range(1, 4) as $i) {
            $this->artisan('app:poll-live-scores')->assertSuccessful();
        }

        Notification::assertSentToTimes($fan, GoalScored::class, 1);
    }

    public function test_a_finished_match_pushes_full_time_once(): void
    {
        $fan = $this->fanOf('competition', '2000');

        // Live first, then vanished from the bulk feed with a FINISHED record:
        // recordFinal appends the FT event (once) and pushes the final score.
        Cache::put('live:empty-streak', 2, 300);

        Http::fake([
            '*/matches/1' => Http::response($this->upstreamMatch(1, 'FINISHED', 2, 0), 200),
            '*/matches*' => Http::sequence()
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', 2, 0)]], 200)
                ->push(['matches' => []], 200)
                ->push(['matches' => []], 200)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();
        Cache::put('live:empty-streak', 2, 300);
        $this->artisan('app:poll-live-scores')->assertSuccessful();
        Cache::put('live:empty-streak', 2, 300);
        $this->artisan('app:poll-live-scores')->assertSuccessful();

        Notification::assertSentToTimes($fan, MatchFullTime::class, 1);
    }
}
