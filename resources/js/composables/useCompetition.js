import { toValue } from 'vue';

import { useApi } from '@/composables/useApi';

export function useCompetition(id) {
    return useApi(() => `/competitions/${toValue(id)}`);
}
