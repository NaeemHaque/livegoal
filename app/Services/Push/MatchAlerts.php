<?php

namespace App\Services\Push;

use App\Models\PushSubscriber;
use App\Notifications\GoalScored;
use App\Notifications\MatchFullTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

/**
 * Resolves who follows a match (either team, or its competition) and queues
 * the web-push notifications. Called by the live poller at the exact points
 * where GOAL/FT timeline events are appended — those appends happen once per
 * real event, so no dedupe is needed here. See docs/PUSH_NOTIFICATIONS.md.
 */
class MatchAlerts
{
    /**
     * @param  array<array-key, mixed>  $m  A normalized live match.
     */
    public function goalScored(array $m): void
    {
        $this->notifyFollowers($m, fn (array $payload): BaseNotification => new GoalScored($payload));
    }

    /**
     * @param  array<array-key, mixed>  $m  A normalized live match (final snapshot).
     */
    public function fullTime(array $m): void
    {
        $this->notifyFollowers($m, fn (array $payload): BaseNotification => new MatchFullTime($payload));
    }

    /**
     * @param  array<array-key, mixed>  $m
     * @param  \Closure(array{id: string, url: string, homeName: string, awayName: string, homeScore: int|null, awayScore: int|null, minute: int|null, competition: string, crest: string|null}): BaseNotification  $make
     */
    private function notifyFollowers(array $m, \Closure $make): void
    {
        // Un-keyed installs (VAPID not generated yet) never enqueue anything.
        $publicKey = Config::get('webpush.vapid.public_key');
        $privateKey = Config::get('webpush.vapid.private_key');

        if (! is_string($publicKey) || $publicKey === '' || ! is_string($privateKey) || $privateKey === '') {
            return;
        }

        $payload = $this->payload($m);

        if ($payload === null) {
            return;
        }

        $teamIds = array_values(array_filter([
            $this->str($this->side($m, 'home')['id'] ?? null),
            $this->str($this->side($m, 'away')['id'] ?? null),
        ], fn (string $id): bool => $id !== ''));

        $competition = is_array($m['competition'] ?? null) ? $m['competition'] : [];
        $competitionId = $this->str($competition['id'] ?? null);

        if ($teamIds === [] && $competitionId === '') {
            return;
        }

        $notification = $make($payload);

        $this->audience($teamIds, $competitionId)
            ->chunkById(500, function ($subscribers) use ($notification): void {
                Notification::send($subscribers, $notification);
            });
    }

    /**
     * Subscribers following either team or the competition — one row each,
     * however many of those they follow.
     *
     * @param  list<string>  $teamIds
     * @return Builder<PushSubscriber>
     */
    private function audience(array $teamIds, string $competitionId): Builder
    {
        return PushSubscriber::query()->whereHas('follows', function (Builder $query) use ($teamIds, $competitionId): void {
            $query->where(function (Builder $q) use ($teamIds, $competitionId): void {
                if ($teamIds !== []) {
                    $q->orWhere(fn (Builder $sub): Builder => $sub->where('type', 'team')->whereIn('followed_id', $teamIds));
                }

                if ($competitionId !== '') {
                    $q->orWhere(fn (Builder $sub): Builder => $sub->where('type', 'competition')->where('followed_id', $competitionId));
                }
            });
        });
    }

    /**
     * The slim notification payload — kept small so queued jobs stay light.
     *
     * @param  array<array-key, mixed>  $m
     * @return array{id: string, url: string, homeName: string, awayName: string, homeScore: int|null, awayScore: int|null, minute: int|null, competition: string, crest: string|null}|null
     */
    private function payload(array $m): ?array
    {
        $id = $this->str($m['id'] ?? null);
        $home = $this->side($m, 'home');
        $away = $this->side($m, 'away');
        $homeName = $this->str($home['name'] ?? null);
        $awayName = $this->str($away['name'] ?? null);

        if ($id === '' || $homeName === '' || $awayName === '') {
            return null;
        }

        $competition = is_array($m['competition'] ?? null) ? $m['competition'] : [];
        $crest = $home['crest'] ?? null;

        return [
            'id' => $id,
            'url' => '/match/'.$id,
            'homeName' => $homeName,
            'awayName' => $awayName,
            'homeScore' => $this->nullableInt($m['homeScore'] ?? null),
            'awayScore' => $this->nullableInt($m['awayScore'] ?? null),
            'minute' => $this->nullableInt($m['minute'] ?? null),
            'competition' => $this->str($competition['short'] ?? $competition['name'] ?? null),
            'crest' => is_string($crest) && $crest !== '' ? $crest : null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $m
     * @return array<array-key, mixed>
     */
    private function side(array $m, string $key): array
    {
        return is_array($m[$key] ?? null) ? $m[$key] : [];
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }
}
