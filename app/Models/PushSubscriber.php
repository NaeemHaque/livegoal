<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * An anonymous browser that enabled match alerts (no user accounts — see
 * docs/PUSH_NOTIFICATIONS.md). Owns one web-push subscription (package morph)
 * and a follow snapshot in push_follows synced from the client's localStorage.
 */
class PushSubscriber extends Model
{
    use HasPushSubscriptions;
    use Notifiable;
    use Prunable;

    /**
     * @return HasMany<PushFollow, $this>
     */
    public function follows(): HasMany
    {
        return $this->hasMany(PushFollow::class);
    }

    /**
     * Subscribers whose endpoint expired (the webpush channel deletes the
     * subscription row on 404/410 sends) linger as orphans; sweep them after
     * a week via the scheduled `model:prune`.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereDoesntHave('pushSubscriptions')
            ->where('updated_at', '<', now()->subWeek());
    }
}
