<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * A goal in a match the subscriber follows. Sent the moment the live poller
 * records the GOAL timeline event (its dedupe and flap guards gate this), so
 * one real goal produces exactly one push. See docs/PUSH_NOTIFICATIONS.md.
 */
class GoalScored extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{id: string, url: string, homeName: string, awayName: string, homeScore: int|null, awayScore: int|null, minute: int|null, competition: string, crest: string|null}  $match
     */
    public function __construct(public array $match) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $m = $this->match;
        $minute = $m['minute'] !== null ? "{$m['minute']}' — " : '';

        return (new WebPushMessage)
            ->title("GOAL! {$m['homeName']} {$m['homeScore']}–{$m['awayScore']} {$m['awayName']}")
            ->body($minute.$m['competition'])
            ->icon($m['crest'] ?? '/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->tag('match-'.$m['id'])
            ->renotify()
            ->data(['url' => $m['url']])
            ->options(['TTL' => 600, 'urgency' => 'high', 'topic' => 'm'.$m['id']]);
    }
}
