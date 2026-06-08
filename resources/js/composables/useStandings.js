import { toValue } from 'vue';

import { useApi } from '@/composables/useApi';

export function useStandings(id) {
    return useApi(() => `/competitions/${toValue(id)}/standings`);
}
