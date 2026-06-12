import { useDocumentVisibility, useIntervalFn } from '@vueuse/core';
import { computed, watch } from 'vue';

import api from '@/services/api';
import { useMatchesStore } from '@/stores/matches';
import { useSettingsStore } from '@/stores/settings';

/**
 * Drives the site-wide live feed: polls GET /api/live into the matches store on
 * an interval, pausing while the tab is hidden (or when the user pauses), and
 * refetching once on resume. Cache-served upstream, so this is cheap.
 */
export function useLiveMatches() {
    const matches = useMatchesStore();
    const settings = useSettingsStore();
    const visibility = useDocumentVisibility();

    async function poll() {
        matches.loading = true;

        try {
            const res = await api.get('/live');
            matches.setLive(
                res.data?.data?.matches,
                res.data?.meta ?? {},
                res.data?.data?.finals,
            );
        } catch (e) {
            matches.setError(e);
        } finally {
            matches.loading = false;
        }
    }

    const interval = computed(() => Math.max(5, settings.refresh) * 1000);
    const { pause, resume } = useIntervalFn(poll, interval, {
        immediate: false,
    });

    watch(
        [visibility, () => settings.paused],
        () => {
            if (visibility.value === 'visible' && !settings.paused) {
                poll();
                resume();
            } else {
                pause();
            }
        },
        { immediate: true },
    );

    return {
        live: computed(() => matches.live),
        lastUpdated: computed(() => matches.lastUpdated),
        loading: computed(() => matches.loading),
        refresh: poll,
    };
}
