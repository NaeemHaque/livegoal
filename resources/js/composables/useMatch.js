import { toValue } from 'vue';

import { useApi } from '@/composables/useApi';

export function useMatch(id) {
    return useApi(() => `/matches/${toValue(id)}`);
}
