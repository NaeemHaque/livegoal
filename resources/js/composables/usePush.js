import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';

import api from '@/services/api';
import { useFavoritesStore } from '@/stores/favorites';
import { useSettingsStore } from '@/stores/settings';

/**
 * Web-push match alerts (see docs/PUSH_NOTIFICATIONS.md): permission +
 * subscription lifecycle, and keeping the server's follow snapshot in sync
 * with the localStorage favorites. The native permission prompt only ever
 * fires from `enable()` — a real user gesture.
 */

const supported =
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window;

// Module-level so every component shares one reactive permission state.
const permission = ref(supported ? Notification.permission : 'denied');

// Why the last enable() attempt failed, for the Settings row to display.
const lastError = ref(null);

let followWatcherStarted = false;

const vapidPublicKey = () =>
    document.querySelector('meta[name="vapid-public-key"]')?.content ?? '';

// PushManager.subscribe wants the VAPID key as a Uint8Array.
const urlBase64ToUint8Array = (base64) => {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
};

const getSubscription = async () => {
    const registration =
        await navigator.serviceWorker.getRegistration('/sw.js');

    return registration ? registration.pushManager.getSubscription() : null;
};

const subscribe = async () => {
    const registration = await navigator.serviceWorker.register('/sw.js');

    return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey()),
    });
};

const syncToServer = (subscription, follows) => {
    const { endpoint, keys } = subscription.toJSON();

    return api.post('/push/subscriptions', {
        endpoint,
        keys,
        contentEncoding: (PushManager.supportedContentEncodings ?? [
            'aes128gcm',
        ])[0],
        ...(follows
            ? { follows: follows.map((f) => ({ type: f.type, id: f.id })) }
            : {}),
    });
};

export function usePush() {
    const settings = useSettingsStore();
    const favorites = useFavoritesStore();

    // Re-sync the server's follow snapshot whenever the follows change.
    const startFollowWatcher = () => {
        if (followWatcherStarted) {
            return;
        }

        followWatcherStarted = true;

        watchDebounced(
            () => favorites.items,
            async () => {
                if (!settings.pushEnabled || permission.value !== 'granted') {
                    return;
                }

                const subscription = await getSubscription();

                if (subscription) {
                    await syncToServer(subscription, favorites.items).catch(
                        () => {},
                    );
                }
            },
            { debounce: 1500, deep: true },
        );
    };

    /** Gesture path: prompt, subscribe, sync. Returns whether alerts are on. */
    const enable = async () => {
        lastError.value = null;

        if (!supported || !vapidPublicKey()) {
            return false;
        }

        permission.value = await Notification.requestPermission();

        if (permission.value !== 'granted') {
            return false;
        }

        try {
            const subscription = await subscribe();
            await syncToServer(subscription, favorites.items);
        } catch (error) {
            // Typical causes: the browser's push service is disabled (Brave
            // ships "Use Google services for push messaging" off) or blocked
            // by the network. Surface it instead of failing silently.
            lastError.value =
                error?.name === 'AbortError'
                    ? 'Your browser could not reach its push service — in Brave, enable "Use Google services for push messaging" (Settings → Privacy) and relaunch.'
                    : 'Could not subscribe — check the browser console.';

            return false;
        }

        settings.pushEnabled = true;
        startFollowWatcher();

        return true;
    };

    const disable = async () => {
        settings.pushEnabled = false;

        const subscription = await getSubscription();

        if (subscription) {
            await api
                .delete('/push/subscriptions', {
                    data: { endpoint: subscription.endpoint },
                })
                .catch(() => {});
            await subscription.unsubscribe().catch(() => {});
        }
    };

    /** App boot: repair drift, or quietly turn off if permission was revoked. */
    const init = async () => {
        if (!supported || !settings.pushEnabled) {
            return;
        }

        permission.value = Notification.permission;

        if (permission.value !== 'granted') {
            settings.pushEnabled = false;

            return;
        }

        try {
            const subscription = await subscribe();
            await syncToServer(subscription, favorites.items);
        } catch {
            // Offline or push service hiccup — the next boot retries.
        }

        startFollowWatcher();
    };

    return { supported, permission, lastError, enable, disable, init };
}
