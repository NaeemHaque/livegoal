<?php

namespace Tests\Feature\Push;

use App\Models\PushSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * Covers the push-subscriber pruning scope (docs/PUSH_NOTIFICATIONS.md):
 * subscribers whose web-push subscription rows are gone (expired endpoints are
 * deleted by the webpush channel on 404/410 sends) are swept a week later by
 * `model:prune`; subscribers with a live subscription — or freshly synced —
 * are never touched.
 */
class PushSubscriberPruningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function subscriberUpdatedAt(string $at): PushSubscriber
    {
        $subscriber = PushSubscriber::create();
        $subscriber->timestamps = false;
        $subscriber->forceFill(['updated_at' => $at])->save();

        return $subscriber;
    }

    public function test_it_prunes_only_week_old_subscribers_without_a_subscription(): void
    {
        Date::setTestNow('2026-06-12T12:00:00Z');

        $staleOrphan = $this->subscriberUpdatedAt('2026-06-01 12:00:00');
        $freshOrphan = $this->subscriberUpdatedAt('2026-06-10 12:00:00');

        $staleButSubscribed = $this->subscriberUpdatedAt('2026-06-01 12:00:00');
        $staleButSubscribed->updatePushSubscription('https://push.example/ep-1', 'p256dh-key', 'auth-token');

        $pruned = (new PushSubscriber)->prunable()->pluck('id');

        $this->assertContains($staleOrphan->id, $pruned);
        $this->assertNotContains($freshOrphan->id, $pruned);
        $this->assertNotContains($staleButSubscribed->id, $pruned);
    }

    public function test_deleting_a_subscriber_cascades_to_its_follows(): void
    {
        $subscriber = PushSubscriber::create();
        $subscriber->follows()->createMany([
            ['type' => 'team', 'followed_id' => '769'],
            ['type' => 'competition', 'followed_id' => '2000'],
        ]);

        $this->assertDatabaseCount('push_follows', 2);

        $subscriber->delete();

        $this->assertDatabaseCount('push_follows', 0);
    }
}
