import { useApi } from '@/composables/useApi';

/**
 * The latest finished fixtures across featured competitions (server-aggregated,
 * newest first) — the Finished view when no date is selected.
 */
export function useResults() {
    return useApi('/matches/results');
}
