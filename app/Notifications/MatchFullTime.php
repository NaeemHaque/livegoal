<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Full time in a match the subscriber follows. Shares the goal push's tag so
 * the final score replaces any lingering goal notification instead of
 * stacking. See docs/PUSH_NOTIFICATIONS.md.
 */
class MatchFullTime extends Notification implements ShouldQueue
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

        return (new WebPushMessage)
            ->title("FT: {$m['homeName']} {$m['homeScore']}–{$m['awayScore']} {$m['awayName']}")
            ->body($m['competition'])
            ->icon($m['crest'] ?? '/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->tag('match-'.$m['id'])
            ->data(['url' => $m['url']])
            ->options(['TTL' => 1800, 'topic' => 'm'.$m['id']]);
    }
}
