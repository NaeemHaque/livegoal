import { ref, toValue, watch } from 'vue';

import { FEATURED } from '@/lib/featured';
import api from '@/services/api';

/**
 * Fixtures for a single day, aggregated across featured competitions.
 *
 * The free tier's global /matches feed only returns currently-active
 * competitions, so we fan out to each competition's (cached) scoped endpoint and
 * merge — one request per competition, all server-cached.
 */
export function useDayMatches(date) {
    const matches = ref([]);
    const loading = ref(false);
    const error = ref(null);
    let token = 0;

    async function load() {
        const id = ++token;
        loading.value = true;
        error.value = null;
        const d = toValue(date);

        try {
            const results = await Promise.all(
                FEATURED.map((code) =>
                    api
                        .get(`/competitions/${code}/matches`, {
                            params: { dateFrom: d, dateTo: d },
                        })
                        .then((r) => r.data?.data ?? [])
                        .catch(() => []),
                ),
            );

            if (id !== token) {
                return;
            }

            matches.value = results
                .flat()
                .sort((a, b) =>
                    String(a.kickoff ?? '').localeCompare(
                        String(b.kickoff ?? ''),
                    ),
                );
        } catch (e) {
            if (id === token) {
                error.value = e;
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
