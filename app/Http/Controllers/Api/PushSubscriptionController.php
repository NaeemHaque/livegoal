<?php

namespace App\Http\Controllers\Api;

use App\Models\PushSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use NotificationChannels\WebPush\PushSubscription;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anonymous web-push subscriptions (no user accounts): each browser endpoint
 * owns one PushSubscriber carrying its follow snapshot, synced from the
 * client's localStorage on every change. See docs/PUSH_NOTIFICATIONS.md.
 */
class PushSubscriptionController extends Controller
{
    /**
     * Create or update a subscription and (when given) replace its follows.
     *
     * `oldEndpoint` re-keys an existing subscriber after the browser rotates
     * the subscription (SW `pushsubscriptionchange`), keeping its follows.
     */
    public function store(Request $request): Response
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'max:500', 'url:https'],
            'oldEndpoint' => ['sometimes', 'nullable', 'string', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['sometimes', 'nullable', 'string', 'in:aesgcm,aes128gcm'],
            'follows' => ['sometimes', 'array', 'max:200'],
            'follows.*.type' => ['required', 'string', 'in:team,competition'],
            'follows.*.id' => ['required', 'string', 'max:32'],
        ]);

        $endpoint = (string) $request->string('endpoint');
        $oldEndpoint = $request->filled('oldEndpoint') ? (string) $request->string('oldEndpoint') : null;

        $subscriber = $this->subscriberFor($endpoint)
            ?? ($oldEndpoint !== null ? $this->subscriberFor($oldEndpoint) : null)
            ?? PushSubscriber::create();

        // The trait reassigns an endpoint already owned by another subscriber,
        // which makes double-subscribes and endpoint rotation idempotent.
        $subscriber->updatePushSubscription(
            $endpoint,
            (string) $request->string('keys.p256dh'),
            (string) $request->string('keys.auth'),
            $request->filled('contentEncoding') ? (string) $request->string('contentEncoding') : null,
        );

        if ($oldEndpoint !== null && $oldEndpoint !== $endpoint) {
            $subscriber->deletePushSubscription($oldEndpoint);
        }

        if ($request->has('follows')) {
            $this->replaceFollows($subscriber, $request->input('follows'));
        }

        $subscriber->touch();

        return response()->noContent();
    }

    /**
     * Remove a subscription and its subscriber + follows. Idempotent.
     */
    public function destroy(Request $request): Response
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $subscriber = $this->subscriberFor((string) $request->string('endpoint'));

        if ($subscriber !== null) {
            $subscriber->pushSubscriptions()->delete();
            $subscriber->delete();
        }

        return response()->noContent();
    }

    /**
     * Replace the subscriber's follow snapshot wholesale.
     */
    private function replaceFollows(PushSubscriber $subscriber, mixed $follows): void
    {
        $rows = [];

        foreach (is_array($follows) ? $follows : [] as $follow) {
            if (is_array($follow) && is_string($follow['type'] ?? null) && is_string($follow['id'] ?? null)) {
                $rows[] = ['type' => $follow['type'], 'followed_id' => $follow['id']];
            }
        }

        DB::transaction(function () use ($subscriber, $rows): void {
            $subscriber->follows()->delete();
            $subscriber->follows()->createMany($rows);
        });
    }

    /**
     * The subscriber owning an endpoint, if any.
     */
    private function subscriberFor(string $endpoint): ?PushSubscriber
    {
        $subscription = PushSubscription::findByEndpoint($endpoint);
        $owner = $subscription?->subscribable;

        return $owner instanceof PushSubscriber ? $owner : null;
    }
}
