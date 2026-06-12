<?php

namespace Tests\Feature\Api;

use App\Models\PushSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the anonymous push-subscription API (docs/PUSH_NOTIFICATIONS.md):
 * POST upserts one subscriber per endpoint with its follow snapshot (replaced
 * wholesale when present, untouched when omitted), `oldEndpoint` re-keys a
 * rotated subscription without losing follows, and DELETE removes the
 * subscriber with everything it owns — idempotently.
 */
class PushSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'endpoint' => 'https://push.example/ep-1',
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'],
            'contentEncoding' => 'aes128gcm',
            'follows' => [
                ['type' => 'team', 'id' => '769'],
                ['type' => 'competition', 'id' => '2000'],
            ],
        ], $overrides);
    }

    public function test_it_creates_a_subscriber_with_subscription_and_follows(): void
    {
        $this->postJson('/api/push/subscriptions', $this->payload())
            ->assertNoContent();

        $this->assertDatabaseCount('push_subscribers', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => 'https://push.example/ep-1',
            'public_key' => 'p256dh-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aes128gcm',
        ]);
        $this->assertDatabaseHas('push_follows', ['type' => 'team', 'followed_id' => '769']);
        $this->assertDatabaseHas('push_follows', ['type' => 'competition', 'followed_id' => '2000']);
    }

    public function test_reposting_the_same_endpoint_updates_in_place_without_a_second_subscriber(): void
    {
        $this->postJson('/api/push/subscriptions', $this->payload())->assertNoContent();

        $this->postJson('/api/push/subscriptions', $this->payload([
            'keys' => ['p256dh' => 'rotated-key', 'auth' => 'rotated-token'],
            'follows' => [['type' => 'team', 'id' => '772']],
        ]))->assertNoContent();

        $this->assertDatabaseCount('push_subscribers', 1);
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', ['public_key' => 'rotated-key']);

        // Follows were replaced wholesale, not appended.
        $this->assertDatabaseCount('push_follows', 1);
        $this->assertDatabaseHas('push_follows', ['type' => 'team', 'followed_id' => '772']);
    }

    public function test_old_endpoint_rekeys_the_subscriber_and_keeps_its_follows(): void
    {
        $this->postJson('/api/push/subscriptions', $this->payload())->assertNoContent();
        $subscriber = PushSubscriber::sole();

        // Browser rotated the endpoint (SW pushsubscriptionchange): no follows sent.
        $this->postJson('/api/push/subscriptions', [
            'endpoint' => 'https://push.example/ep-2',
            'oldEndpoint' => 'https://push.example/ep-1',
            'keys' => ['p256dh' => 'new-key', 'auth' => 'new-token'],
        ])->assertNoContent();

        $this->assertDatabaseCount('push_subscribers', 1);
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => 'https://push.example/ep-2',
            'subscribable_id' => $subscriber->id,
        ]);
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example/ep-1']);

        // The follow snapshot survived the rotation untouched.
        $this->assertDatabaseCount('push_follows', 2);
    }

    public function test_omitting_follows_leaves_the_snapshot_untouched(): void
    {
        $this->postJson('/api/push/subscriptions', $this->payload())->assertNoContent();

        $this->postJson('/api/push/subscriptions', [
            'endpoint' => 'https://push.example/ep-1',
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'],
        ])->assertNoContent();

        $this->assertDatabaseCount('push_follows', 2);
    }

    public function test_an_empty_follows_array_clears_the_snapshot(): void
    {
        $this->postJson('/api/push/subscriptions', $this->payload())->assertNoContent();

        $this->postJson('/api/push/subscriptions', $this->payload(['follows' => []]))
            ->assertNoContent();

        $this->assertDatabaseCount('push_follows', 0);
    }

    public function test_it_validates_the_payload(): void
    {
        // Missing endpoint.
        $this->postJson('/api/push/subscriptions', ['keys' => ['p256dh' => 'k', 'auth' => 'a']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');

        // Non-https endpoint.
        $this->postJson('/api/push/subscriptions', $this->payload([
            'endpoint' => 'http://insecure.example/ep',
        ]))->assertUnprocessable()->assertJsonValidationErrors('endpoint');

        // Missing keys.
        $this->postJson('/api/push/subscriptions', [
            'endpoint' => 'https://push.example/ep-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('keys');

        // Unknown follow type.
        $this->postJson('/api/push/subscriptions', $this->payload([
            'follows' => [['type' => 'player', 'id' => '1']],
        ]))->assertUnprocessable()->assertJsonValidationErrors('follows.0.type');

        $this->assertDatabaseCount('push_subscribers', 0);
    }

    public function test_destroy_removes_subscriber_subscription_and_follows(): void
    {
        $this->postJson('/api/push/subscriptions', $this->payload())->assertNoContent();

        $this->deleteJson('/api/push/subscriptions', ['endpoint' => 'https://push.example/ep-1'])
            ->assertNoContent();

        $this->assertDatabaseCount('push_subscribers', 0);
        $this->assertDatabaseCount('push_subscriptions', 0);
        $this->assertDatabaseCount('push_follows', 0);

        // Idempotent: deleting again is still a 204.
        $this->deleteJson('/api/push/subscriptions', ['endpoint' => 'https://push.example/ep-1'])
            ->assertNoContent();
    }
}
