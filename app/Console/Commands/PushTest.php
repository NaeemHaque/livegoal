<?php

namespace App\Console\Commands;

use App\Models\PushSubscriber;
use App\Notifications\GoalScored;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\PushSubscription;

/**
 * End-to-end smoke test for the push pipeline (VAPID keys, queue worker,
 * service worker): sends a demo goal notification to every subscriber, or to
 * the one owning --endpoint. See docs/PUSH_NOTIFICATIONS.md.
 */
class PushTest extends Command
{
    protected $signature = 'app:push-test {--endpoint= : Only the subscriber owning this endpoint}';

    protected $description = 'Send a test web-push notification to subscribers';

    public function handle(): int
    {
        $demo = [
            'id' => 'test',
            'url' => '/',
            'homeName' => 'LiveGoal',
            'awayName' => 'Push Test',
            'homeScore' => 1,
            'awayScore' => 0,
            'minute' => 90,
            'competition' => 'If you can read this, alerts work',
            'crest' => null,
        ];

        $endpoint = $this->option('endpoint');

        if (is_string($endpoint) && $endpoint !== '') {
            $owner = PushSubscription::findByEndpoint($endpoint)?->subscribable;

            if (! $owner instanceof PushSubscriber) {
                $this->error('No subscriber owns that endpoint.');

                return self::FAILURE;
            }

            $owner->notify(new GoalScored($demo));
            $this->info('Queued a test push for 1 subscriber.');

            return self::SUCCESS;
        }

        $count = 0;

        PushSubscriber::query()->whereHas('pushSubscriptions')->chunkById(500, function ($subscribers) use ($demo, &$count): void {
            Notification::send($subscribers, new GoalScored($demo));
            $count += $subscribers->count();
        });

        $this->info(sprintf('Queued a test push for %d subscriber(s).', $count));

        return self::SUCCESS;
    }
}
