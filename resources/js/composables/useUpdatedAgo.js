import { useNow } from '@vueuse/core';
import { computed, toValue } from 'vue';

/**
 * Reactive compact "updated Xs ago" string from an ISO timestamp, ticking every
 * second. Empty string when no timestamp.
 *
 * @param {import('vue').MaybeRefOrGetter<string|null>} lastUpdated
 */
export function useUpdatedAgo(lastUpdated) {
    const now = useNow({ interval: 1000 });

    return computed(() => {
        const iso = toValue(lastUpdated);

        if (!iso) {
            return '';
        }

        const seconds = Math.max(
            0,
            Math.round((now.value.getTime() - new Date(iso).getTime()) / 1000),
        );

        if (seconds < 5) {
            return 'just now';
        }

        if (seconds < 60) {
            return `${seconds}s ago`;
        }

        const minutes = Math.floor(seconds / 60);

        if (minutes < 60) {
            return `${minutes}m ago`;
        }

        return `${Math.floor(minutes / 60)}h ago`;
    });
}
