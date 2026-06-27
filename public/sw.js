/**
 * LiveGoal service worker — push only, no fetch handling or offline caching.
 *
 * Shows goal / full-time notifications sent by the server poller (see
 * docs/PUSH_NOTIFICATIONS.md), except when a LiveGoal window is visible —
 * the in-app goal toast owns that case.
 */

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload;

    try {
        payload = event.data.json();
    } catch {
        return;
    }

    const { title, ...options } = payload;

    if (!title) {
        return;
    }

    event.waitUntil(
        (async () => {
            const windows = await self.clients.matchAll({
                type: 'window',
                includeUncontrolled: true,
            });

            const visible = windows.some(
                (client) => client.visibilityState === 'visible',
            );

            if (visible) {
                return; // The in-app toast covers visible tabs.
            }

            await self.registration.showNotification(title, options);
        })(),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        (async () => {
            const windows = await self.clients.matchAll({
                type: 'window',
                includeUncontrolled: true,
            });

            if (windows.length > 0) {
                const client = windows[0];
                await client.focus();

                if ('navigate' in client) {
                    await client.navigate(url);
                }

                return;
            }

            await self.clients.openWindow(url);
        })(),
    );
});

self.addEventListener('pushsubscriptionchange', (event) => {
    // The browser rotated the subscription: re-subscribe with the same key
    // and re-key the server-side subscriber (follows are preserved there).
    event.waitUntil(
        (async () => {
            const oldEndpoint = event.oldSubscription
                ? event.oldSubscription.endpoint
                : null;
            const key = event.oldSubscription
                ? event.oldSubscription.options.applicationServerKey
                : null;

            if (!key) {
                return;
            }

            const subscription = await self.registration.pushManager.subscribe(
                { userVisibleOnly: true, applicationServerKey: key },
            );
            const json = subscription.toJSON();

            await fetch('/api/push/subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    endpoint: json.endpoint,
                    oldEndpoint,
                    keys: json.keys,
                }),
            });
        })(),
    );
});
