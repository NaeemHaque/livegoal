<?php

namespace Tests\Feature\Push;

use App\Models\PushSubscriber;
use App\Notifications\GoalScored;
use App\Notifications\MatchFullTime;
use App\Services\Push\MatchAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Covers audience resolution for match alerts (docs/PUSH_NOTIFICATIONS.md):
 * subscribers following either team or the competition get exactly one
 * notification per event — including those following both — and un-keyed
 * installs (no VAPID) never send anything.
 */
class MatchAlertsTest extends TestCase
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
            'minute' => 16,
            'homeScore' => 1,
            'awayScore' => 0,
            'home' => ['id' => '769', 'name' => 'Mexico', 'tla' => 'MEX', 'crest' => 'https://crests.example/769.svg'],
            'away' => ['id' => '774', 'name' => 'South Africa', 'tla' => 'RSA', 'crest' => null],
            'competition' => ['id' => '2000', 'code' => 'WC', 'name' => 'FIFA World Cup', 'short' => 'World Cup'],
            'kickoff' => '2026-06-11T19:00:00Z',
        ];
    }

    private function subscriberFollowing(string $type, string $id): PushSubscriber
    {
        $subscriber = PushSubscriber::create();
        $subscriber->follows()->create(['type' => $type, 'followed_id' => $id]);

        return $subscriber;
    }

    public function test_it_notifies_followers_of_either_team_or_the_competition_exactly_once(): void
    {
        $homeFan = $this->subscriberFollowing('team', '769');
        $awayFan = $this->subscriberFollowing('team', '774');
        $compFan = $this->subscriberFollowing('competition', '2000');
        $unrelated = $this->subscriberFollowing('team', '999');

        // Follows both the home team and the competition: still one send.
        $both = $this->subscriberFollowing('team', '769');
        $both->follows()->create(['type' => 'competition', 'followed_id' => '2000']);

        app(MatchAlerts::class)->goalScored($this->match());

        Notification::assertSentTo($homeFan, GoalScored::class);
        Notification::assertSentTo($awayFan, GoalScored::class);
        Notification::assertSentTo($compFan, GoalScored::class);
        Notification::assertNotSentTo($unrelated, GoalScored::class);
        Notification::assertSentToTimes($both, GoalScored::class, 1);
    }

    public function test_full_time_uses_the_same_audience(): void
    {
        $fan = $this->subscriberFollowing('team', '774');

        app(MatchAlerts::class)->fullTime($this->match());

        Notification::assertSentTo($fan, MatchFullTime::class);
    }

    public function test_no_vapid_keys_means_no_sends(): void
    {
        Config::set('webpush.vapid.public_key', '');

        $this->subscriberFollowing('team', '769');

        app(MatchAlerts::class)->goalScored($this->match());

        Notification::assertNothingSent();
    }

    public function test_a_match_without_identifiable_teams_sends_nothing(): void
    {
        $this->subscriberFollowing('team', '769');

        app(MatchAlerts::class)->goalScored(['id' => '1', 'home' => [], 'away' => []]);

        Notification::assertNothingSent();
    }

    public function test_the_goal_payload_renders_the_scoreline_and_click_target(): void
    {
        $fan = $this->subscriberFollowing('team', '769');

        app(MatchAlerts::class)->goalScored($this->match());

        Notification::assertSentTo($fan, GoalScored::class, function (GoalScored $notification) use ($fan): bool {
            $message = $notification->toWebPush($fan, $notification)->toArray();
            $options = $notification->toWebPush($fan, $notification)->getOptions();

            return $message['title'] === 'GOAL! Mexico 1–0 South Africa'
                && $message['body'] === "16' — World Cup"
                && $message['tag'] === 'match-537327'
                && $message['data'] === ['url' => '/match/537327']
                && $options['TTL'] === 600;
        });
    }
}
