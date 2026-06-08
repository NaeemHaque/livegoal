import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

/**
 * Shared live-match state, filled by the `useLiveMatches` polling composable and
 * read by the live hub, ticker, and match pages.
 */
export const useMatchesStore = defineStore('matches', () => {
    const live = ref([]);
    const lastUpdated = ref(null);
    const stale = ref(false);
    const loading = ref(false);
    const error = ref(null);

    const liveCount = computed(() => live.value.length);

    const byId = (id) =>
        live.value.find((m) => String(m.id) === String(id)) ?? null;

    function setLive(matches, meta = {}) {
        live.value = Array.isArray(matches) ? matches : [];
        lastUpdated.value = meta.lastUpdated ?? null;
        stale.value = meta.stale ?? false;
        error.value = null;
    }

    function setError(err) {
        error.value = err;
    }

    return {
        live,
        lastUpdated,
        stale,
        loading,
        error,
        liveCount,
        byId,
        setLive,
        setError,
    };
});
