import { toValue } from 'vue';

import { useApi } from '@/composables/useApi';

export function useScorers(id) {
    return useApi(() => `/competitions/${toValue(id)}/scorers`);
}
