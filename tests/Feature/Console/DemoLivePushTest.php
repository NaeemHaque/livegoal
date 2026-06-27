<?php

namespace Tests\Feature\Console;

use App\Console\Commands\PollLiveScores;
use App\Models\PushSubscriber;
use App\Notifications\GoalScored;
use App\Notifications\MatchFullTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Covers the demo command's push hooks (docs/PUSH_NOTIFICATIONS.md): --goal
 * pushes the bumped match to its followers and --end pushes every demo
 * match's final score, then clears the live cache.
 */
class DemoLivePushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('webpush.vapid.public_key', 'test-public');
        Config::set('webpush.vapid.private_key', 'test-private');

        Notification::fake();

        Cache::put(PollLiveScores::CACHE_KEY, [
            'matches' => [[
                'id' => '537341',
                'status' => 'LIVE',
                'minute' => 60,
                'homeScore' => 1,
                'awayScore' => 0,
                'home' => ['id' => '769', 'name' => 'Mexico', 'tla' => 'MEX', 'crest' => null],
                'away' => ['id' => '764', 'name' => 'Brazil', 'tla' => 'BRA', 'crest' => null],
                'competition' => ['id' => '2000', 'code' => 'WC', 'name' => 'FIFA World Cup', 'short' => 'World Cup'],
                'kickoff' => '2026-06-12T19:00:00Z',
            ]],
            'count' => 1,
            'lastUpdated' => '2026-06-12T19:30:00+00:00',
        ], 600);
    }

    private function fan(): PushSubscriber
    {
        $subscriber = PushSubscriber::create();
        $subscriber->follows()->create(['type' => 'competition', 'followed_id' => '2000']);

        return $subscriber;
    }

    public function test_goal_pushes_to_followers(): void
    {
        $fan = $this->fan();

        $this->artisan('app:demo-live --goal')->assertSuccessful();

        Notification::assertSentToTimes($fan, GoalScored::class, 1);
    }

    public function test_end_pushes_full_time_and_clears_the_demo(): void
    {
        $fan = $this->fan();

        $this->artisan('app:demo-live --end')->assertSuccessful();

        Notification::assertSentToTimes($fan, MatchFullTime::class, 1);
        $this->assertSame([], Cache::get(PollLiveScores::CACHE_KEY)['matches']);
    }
}
