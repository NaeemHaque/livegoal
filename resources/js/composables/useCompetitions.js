import { useApi } from '@/composables/useApi';

export function useCompetitions() {
    return useApi('/competitions');
}
