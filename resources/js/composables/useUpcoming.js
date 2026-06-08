import { useApi } from '@/composables/useApi';

/**
 * The next scheduled fixtures across featured competitions (server-aggregated),
 * so the app surfaces what's coming — e.g. the World Cup — on quiet days.
 */
export function useUpcoming() {
    return useApi('/matches/upcoming');
}
