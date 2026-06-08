import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

/**
 * Shared live-match state, filled by the `useLiveMatches` polling composable and
 * read by the live hub, ticker, and match pages. Also detects goals across polls
 * to drive the goal toast / score-flip animation.
 */
export const useMatchesStore = defineStore('matches', () => {
    const live = ref([]);
    const lastUpdated = ref(null);
    const stale = ref(false);
    const loading = ref(false);
    const error = ref(null);

    const lastGoal = ref(null); // { team, matchId, minute, scoreline }
    const justScoredId = ref(null);
    let justTimer = null;

    const liveCount = computed(() => live.value.length);

    const byId = (id) =>
        live.value.find((m) => String(m.id) === String(id)) ?? null;

    function detectGoals(previous, incoming) {
        if (previous.length === 0) {
            return; // no baseline on first poll — don't fire on initial load
        }

        const prevById = new Map(previous.map((m) => [String(m.id), m]));

        for (const m of incoming) {
            const before = prevById.get(String(m.id));

            if (!before) {
                continue;
            }

            const now = (m.homeScore ?? 0) + (m.awayScore ?? 0);
            const was = (before.homeScore ?? 0) + (before.awayScore ?? 0);

            if (now > was) {
                const homeScored = (m.homeScore ?? 0) > (before.homeScore ?? 0);

                lastGoal.value = {
                    team: homeScored ? m.home : m.away,
                    matchId: String(m.id),
                    minute: m.minute,
                    scoreline: `${m.homeScore ?? 0}–${m.awayScore ?? 0}`,
                };
                justScoredId.value = String(m.id);

                if (justTimer) {
                    clearTimeout(justTimer);
                }

                justTimer = setTimeout(() => (justScoredId.value = null), 1400);
            }
        }
    }

    function setLive(matches, meta = {}) {
        const incoming = Array.isArray(matches) ? matches : [];
        detectGoals(live.value, incoming);
        live.value = incoming;
        lastUpdated.value = meta.lastUpdated ?? null;
        stale.value = meta.stale ?? false;
        error.value = null;
    }

    function setError(err) {
        error.value = err;
    }

    function clearGoal() {
        lastGoal.value = null;
    }

    return {
        live,
        lastUpdated,
        stale,
        loading,
        error,
        lastGoal,
        justScoredId,
        liveCount,
        byId,
        setLive,
        setError,
        clearGoal,
    };
});
