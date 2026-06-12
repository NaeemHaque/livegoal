import { ref, toValue, watch } from 'vue';

import api from '@/services/api';

/**
 * Fixtures for a single day, aggregated across featured competitions.
 *
 * The free tier's global /matches feed only returns currently-active
 * competitions, so GET /api/matches/day merges each competition's scoped feed
 * server-side — one browser request, all server-cached.
 */
export function useDayMatches(date) {
    const matches = ref([]);
    const loading = ref(false);
    const error = ref(null);
    let token = 0;

    async function load() {
        const value = toValue(date);

        // No date selected (the default upcoming view) — nothing to fetch.
        if (!value) {
            token++;
            matches.value = [];
            loading.value = false;
            error.value = null;

            return;
        }

        const id = ++token;
        loading.value = true;
        error.value = null;

        try {
            const res = await api.get('/matches/day', {
                params: { date: value },
            });

            if (id !== token) {
                return;
            }

            matches.value = res.data?.data ?? [];
        } catch (e) {
            if (id === token) {
                error.value = e;
                matches.value = [];
            }
        } finally {
            if (id === token) {
                loading.value = false;
            }
        }
    }

    watch(() => toValue(date), load, { immediate: true });

    return { matches, loading, error, reload: load };
}
