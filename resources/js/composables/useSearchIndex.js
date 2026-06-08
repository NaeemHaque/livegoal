import { ref } from 'vue';

import { compKey, FEATURED } from '@/lib/featured';
import api from '@/services/api';

/**
 * Builds a client-side search index of competitions + teams. The free tier has
 * no search endpoint, so we aggregate the (server-cached) competition list and
 * each featured competition's team list once, then filter locally.
 */
export function useSearchIndex() {
    const index = ref([]);
    const loading = ref(false);

    async function load() {
        loading.value = true;

        try {
            const [competitions, ...teamLists] = await Promise.all([
                api
                    .get('/competitions')
                    .then((r) => r.data?.data ?? [])
                    .catch(() => []),
                ...FEATURED.map((code) =>
                    api
                        .get(`/competitions/${code}/teams`)
                        .then((r) => r.data?.data ?? [])
                        .catch(() => []),
                ),
            ]);

            const teams = new Map();

            for (const team of teamLists.flat()) {
                const id = team?.id;

                if (id != null && !teams.has(String(id))) {
                    teams.set(String(id), {
                        kind: 'Team',
                        id: String(id),
                        name: team.name,
                        route: `/team/${id}`,
                        team,
                    });
                }
            }

            const comps = competitions.map((c) => ({
                kind: 'Competition',
                id: compKey(c),
                name: c.name,
                route: `/competition/${compKey(c)}`,
                color: c.color,
            }));

            index.value = [...comps, ...teams.values()];
        } finally {
            loading.value = false;
        }
    }

    load();

    return { index, loading };
}
